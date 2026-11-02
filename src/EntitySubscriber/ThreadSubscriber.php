<?php

namespace Base\EntitySubscriber;

use Base\Entity\Thread;
use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Lifecycle\ThreadParticipants;
use Base\Entity\Thread\Mention;
use Base\Entity\ThreadIntl;
use Base\Entity\User\Notification;
use Base\EntityDispatcher\Event\ThreadEvent;
use Base\Enum\ThreadState;
use Base\Service\Model\Wysiwyg\MentionEnhancerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Omnibridge\Persistence\Lifecycle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The threads' kernel events (thread.scheduled, thread.publishable: the state, the authors' and the
 * mentionees' mails) and the Doctrine wiring of their followers and mentions (prePersist, onFlush), whose
 * logic is Base\Database\Lifecycle\ThreadParticipants, on the neutral lifecycle events.
 */
class ThreadSubscriber implements EventSubscriberInterface
{
    /**
     * The enhancer, or a closure that builds it (service_closure): Doctrine builds this listener when its
     * event manager initialises, and the enhancer's repositories read the mapping there (see
     * SettingSubscriber).
     *
     * @var MentionEnhancerInterface|\Closure(): MentionEnhancerInterface
     */
    protected $mentionEnhancer;

    /** @param MentionEnhancerInterface|\Closure(): MentionEnhancerInterface $mentionEnhancer */
    public function __construct(MentionEnhancerInterface|\Closure $mentionEnhancer)
    {
        $this->mentionEnhancer = $mentionEnhancer;
    }

    protected function mentionEnhancer(): MentionEnhancerInterface
    {
        if ($this->mentionEnhancer instanceof \Closure) {
            $this->mentionEnhancer = ($this->mentionEnhancer)();
        }

        return $this->mentionEnhancer;
    }

    public static function getSubscribedEvents(): array
    {
        return
        [
            ThreadEvent::SCHEDULED => ['onSchedule'],
            ThreadEvent::PUBLISHABLE => ['onPublishable']
        ];
    }

    public function onSchedule(ThreadEvent $event)
    {
        $thread = $event->getThread();
        $thread->setState(ThreadState::FUTURE);
    }

    public function onPublishable(ThreadEvent $event)
    {
        $thread = $event->getThread();
        $thread->setState(ThreadState::PUBLISH);

        $authors = $this->authors($thread);
        foreach ($authors as $author) {

            $notification = new Notification('thread.published');
            $notification->setHtmlTemplate("@Base/client/thread/email/publish.html.twig", ["thread" => $thread]);
            $notification->setUser($author);
            $notification->send("email");
        }

        foreach ($thread->getMentions() as $mention) {

            if(in_array($mention->getMentionee(), $authors, true)) continue;

            /**
             * @var Mention $mention
             */
            $notification = new Notification('thread.mentioned');
            $notification->setHtmlTemplate("@Base/client/thread/email/mention.html.twig", ["mentionee" => $mention->getMentionee(), "thread" => $thread]);
            $notification->setUser($mention->getMentionee());
            $notification->send("email");
        }
    }

    /**
     * A thread's authors: its owners, unless its class says otherwise with a getAuthors() of its own
     * (omnibase/marketplace's Product) or an $authors property its magic getter reads (lesringostars'
     * Article and Comment, an alias of the owners). Thread itself has none: the others threw at their
     * publication, after their state.
     *
     * @return object[]
     */
    protected function authors(Thread $thread): array
    {
        $authors = method_exists($thread, 'getAuthors') || property_exists($thread, 'authors') ? $thread->getAuthors() : $thread->getOwners();

        return array_values(array_filter(is_iterable($authors) ? [...$authors] : []));
    }

    /** The followers and mentions, on the neutral lifecycle events; the methods below are their Doctrine wiring. */
    protected function participants(): ThreadParticipants
    {
        return $this->participants ??= new ThreadParticipants(fn () => $this->mentionEnhancer());
    }

    private ?ThreadParticipants $participants = null;

    /** A new reply followed by its parent's owners. */
    public function prePersist(PrePersistEventArgs $event): void
    {
        $this->participants()->onPrePersist(new Lifecycle\PrePersist($event->getObject(), new DoctrineUnitOfWork($event->getObjectManager())));
    }

    /**
     * The mentions of every thread whose text this flush writes (a translation inserted, or its content
     * changed) or that this flush makes publishable (an update that leaves the text alone otherwise -
     * poke(), a counter - parses nothing), and the followers of a reply moved under another parent.
     *
     * The followers are added here, not at preUpdate, where Doctrine writes no collection changed; the
     * thread's change set is then computed again with its collections (computeChangeSet(), Doctrine's:
     * the neutral UnitOfWork::recompute() is recomputeSingleEntityChangeSet(), fields only).
     */
    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $threads = [];
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Thread) {
                $threads[spl_object_id($entity)] = $entity;
            } elseif ($entity instanceof ThreadIntl && $entity->getTranslatable() instanceof Thread) {
                $threads[spl_object_id($entity->getTranslatable())] = $entity->getTranslatable();
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof ThreadIntl && $entity->getTranslatable() instanceof Thread && array_key_exists('content', $unitOfWork->getEntityChangeSet($entity))) {
                $threads[spl_object_id($entity->getTranslatable())] = $entity->getTranslatable();
            }

            // The flush that publishes it (thread.publishable is dispatched after it): its mentions are
            // read again, so that a thread written before its mentions were stored mails them too.
            if ($entity instanceof Thread && $entity->isPublishable()) {
                $threads[spl_object_id($entity)] = $entity;
            }

            if ($entity instanceof Thread && array_key_exists('parent', $unitOfWork->getEntityChangeSet($entity)) && $this->participants()->follow($entity)) {
                $unitOfWork->computeChangeSet($entityManager->getClassMetadata($entity::class), $entity);
            }
        }

        $threads = array_filter($threads, fn (Thread $thread) => !$unitOfWork->isScheduledForDelete($thread));
        if ($threads) {
            $this->participants()->onFlush(new Lifecycle\OnFlush(new DoctrineUnitOfWork($entityManager)), $threads);
        }
    }
}
