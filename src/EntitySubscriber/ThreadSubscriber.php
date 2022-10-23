<?php

namespace Base\EntitySubscriber;

use App\Entity\Marketplace\Product\Extra\Wallpaper\VariantIntl;
use Base\Database\Entity\EntityHydratorInterface;
use Base\Entity\ThreadIntl;
use Base\EntityDispatcher\Event\ThreadEvent;
use Base\EntityDispatcher\Event\ThreadIntlEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ThreadSubscriber implements EventSubscriberInterface
{
    protected array $events;

    /**
     * @var EntityHydratorInterface
     */
    protected $entityHydrator;

    public function __construct(EntityManagerInterface $entityManager, EntityHydratorInterface $entityHydrator)
    {
        $this->entityManager  = $entityManager;
        $this->entityHydrator = $entityHydrator;
    }

    public static function getSubscribedEvents() : array
    {
        return
        [
            ThreadEvent::SCHEDULED   => ['onSchedule'],
            ThreadEvent::PUBLISHABLE => ['onPublishable'],
            ThreadEvent::PUBLISHED   => ['onPublished'],
        ];
    }

    public function onSchedule(ThreadEvent $event)
    {
    }

    public function onPublishable(ThreadEvent $event)
    {
    }

    public function onPublished(ThreadEvent $event)
    {
    }
}
