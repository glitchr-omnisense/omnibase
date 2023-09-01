<?php

namespace Base\EntitySubscriber;

use Base\Entity\Thread;
use Base\Entity\User\Notification;
use Base\EntityDispatcher\Event\ThreadEvent;
use Base\Enum\ThreadState;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 *
 */
class ThreadSubscriber implements EventSubscriberInterface
{
    protected array $events;

    protected $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return
            [
                ThreadEvent::SCHEDULED => ['onSchedule'],
                ThreadEvent::PUBLISHABLE => ['onPublishable'],
                ThreadEvent::PUBLISHED => ['onPublished'],
            ];
    }

    public function onSchedule(ThreadEvent $event)
    {
        $thread = $event->getThread();
        $thread->setState(ThreadState::FUTURE);
    }

    public function onPublished(ThreadEvent $event)
    {
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
    }

    public function prePersist(PrePersistEventArgs $event)
    {
        if(!$event->getObject() instanceof Thread) return;
    }

    public function preUpdate(PreUpdateEventArgs $event)
    {
        if(!$event->getObject() instanceof Thread) return;
        if (!$event->hasChangedField('content')) return;
    }
}
