<?php

namespace Base\DatabaseSubscriber;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Service\LocaleProviderInterface;
use Base\Database\TranslatableInterface;
use Base\Database\TranslationInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

// Optional: Implemnets DiscriminatorInterface
//           Check if discriminator value is same as object type received
//           Cast it into the new object.. and store the one including discriminator interface

class DiscriminatorSubscriber implements EventSubscriber
{
    /**
     * @return string[]
     */
    public function getSubscribedEvents(): array
    {
        return [Events::loadClassMetadata, Events::prePersist, Events::preUpdate];
    }

    public function __construct(ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
    }

    public function prePersist(LifecycleEventArgs $event)
    {
    }

    public function preUpdate(LifecycleEventArgs $event)
    {
    }

    /**
     * Adds mapping to the translatable and translations.
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $loadClassMetadataEventArgs): void
    {
        $classMetadata = $loadClassMetadataEventArgs->getClassMetadata();

        if ($classMetadata->reflClass === null)
            return; // Class has not yet been fully built, ignore this event

        if ($classMetadata->isMappedSuperclass) return;
    }
}
