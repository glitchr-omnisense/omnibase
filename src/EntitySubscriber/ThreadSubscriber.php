<?php

namespace Base\EntitySubscriber;

use App\Entity\Marketplace\Product\Extra\Wallpaper\VariantTranslation;
use Base\Database\Factory\EntityHydratorInterface;
use Base\Entity\ThreadTranslation;
use Base\EntityDispatcher\Event\ThreadEvent;
use Base\EntityDispatcher\Event\ThreadTranslationEvent;
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

            ThreadTranslationEvent::CLEANUP   => ['onCleanup'],
        ];
    }

    public function onCleanup(ThreadTranslationEvent $event)
    {
        $translation = $event->getThreadTranslation();
        $locale = $translation->getLocale();

        $translationParent = $translation->getTranslatable()?->getParent()?->translate($locale);

        $translationData = $this->entityHydrator->dehydrate($translation, ["id", "locale", "translatable"]) ?? [];
        $translationParentData = $this->entityHydrator->dehydrate($translationParent, ["id", "locale", "translatable"]) ?? [];

        foreach($translationData as $key => $data) {

            $parentData = $translationParentData[$key] ?? null;
            $translationData[$key] = $data == $parentData ? null : $data;
        }

        $this->entityHydrator->hydrate($translation, $translationData);
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
