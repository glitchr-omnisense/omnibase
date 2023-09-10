<?php

namespace Base\EntitySubscriber;

use Base\Entity\Thread;
use Base\Entity\Thread\Mention;
use Base\Entity\ThreadIntl;
use Base\Entity\User\Notification;
use Base\EntityDispatcher\Event\ThreadEvent;
use Base\Enum\ThreadState;
use Base\Repository\Thread\MentionRepository;
use Base\Service\Model\Wysiwyg\MentionEnhancerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 *
 */
class ThreadSubscriber implements EventSubscriberInterface
{
    /**
     * @var MentionRepository
     */
    protected $mentionRepository;

    /**
     * @var MentionEnhancerInterface
     */
    protected $mentionEnhancer;

    public function __construct(MentionEnhancerInterface $mentionEnhancer)
    {
        $this->mentionEnhancer = $mentionEnhancer;
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

        foreach ($thread->getAuthors() as $author) {

            $notification = new Notification('thread.published');
            $notification->setHtmlTemplate("@Base/client/thread/email/publish.html.twig", ["thread" => $thread]);
            $notification->setUser($author);
            $notification->send("email");
        }

        foreach ($thread->getMentions() as $mention) {

            if($thread->getAuthors()->contains($mention->getMentionee())) continue;

            /**
             * @var Mention $mention
             */
            $notification = new Notification('thread.mentioned');
            $notification->setHtmlTemplate("@Base/client/thread/email/mention.html.twig", ["mentionee" => $mention->getMentionee(), "thread" => $thread]);
            $notification->setUser($mention->getMentionee());
            $notification->send("email");
        }
    }

    public function prePersist(PrePersistEventArgs $event)
    {
        if(!$event->getObject() instanceof Thread) return;
        
        $thread = $event->getObject();
        $this->updateFollowers($thread);
    }

    public function preUpdate(PreUpdateEventArgs $event)
    {
        if(!$event->getObject() instanceof Thread) return;
        
        $thread = $event->getObject();
        $this->updateFollowers($thread);
    }

    protected function updateFollowers(Thread $thread)
    {
        $parent = $thread->getParent();
        if(!$parent) return;

        foreach($parent->getOwners() as $owner) {
            
            /**
             * @var User $owner
             */
            $thread->addFollower($owner);
        }
    }

    public function onFlush(OnFlushEventArgs $event)
    {
        $uow = $event->getObjectManager()->getUnitOfWork();
        foreach ($uow->getScheduledEntityInsertions() as $entity) {

            if(!$entity instanceof Thread) continue;
            $this->onFlushMentions($event, $entity);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {

            if(!$entity instanceof Thread) continue;
            $this->onFlushMentions($event, $entity);
        }
    }

    protected function onFlushMentions(OnFlushEventArgs $event, Thread $thread)
    {
        $em  = $event->getObjectManager();
        $uow = $em->getUnitOfWork();

        $extractedMentionees = [];
        foreach($thread->getTranslations() as $threadIntl) {

            $content = $threadIntl->getContent();
            $content = is_json($content) ? json_leaves($content) : $content;
            $extractedMentionees = array_unique(array_merge($extractedMentionees, $this->mentionEnhancer->extractMentionees($content)));
        }

        $mentionRepository = $this->mentionEnhancer->getRepository();
        $persistentMentions = $thread->getId() === null ? [] : $mentionRepository->cacheByThread($thread)->getResult();
        $persistentMentionees = array_map(fn($m) => $m->getMentionee(), $persistentMentions);
        
        $newMentionees = array_diff_object($extractedMentionees, $persistentMentionees);
        $oldMentionees = array_diff_object($persistentMentionees, $extractedMentionees);
        $oldMentions   = array_map(fn($k) => $persistentMentions[$k], array_keys($oldMentionees));

        foreach($newMentionees as $mentionee) {

            $mention = new Mention($mentionee, $thread);
            foreach($thread->getOwners() as $mentioner) {

                /**
                 * @var User $mentioner
                 */    
                $mention->addMentioner($mentioner);
            }

            $thread->addMention($mention);
        }

        foreach($oldMentions as $mention) {
            $thread->removeMention($mention);
        }

        if($newMentionees !== [] || $oldMentionees !== []) {
            $uow->recomputeSingleEntityChangeSet($em->getMetadataFactory()->getMetadataFor(Thread::class), $thread);
        }
    }
}
