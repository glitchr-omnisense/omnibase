<?php

namespace Base\Subscriber;

use Base\Attributes\AnnotationReader;
use Base\BaseBundle;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Service\LocalizerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;

/**
 * Guarantees the metadata-critical services exist BEFORE the first entity
 * metadata load. Doctrine's ContainerAwareEventManager instantiates event
 * listeners lazily DURING dispatch; if a loadClassMetadata /
 * resolveDiscriminator listener's own construction triggers another metadata
 * load, the nested dispatch hits a half-initialized event manager
 * (ClassMetadata race). Constructing the listeners' dependency graph up-front
 * — via this subscriber's constructor injection, fired at high priority on
 * every request and console command — closes that window.
 *
 * This used to be done by eagerly constructing BaseService (~25 services:
 * twig, forms, notifier, trading, media, EasyAdmin, ... ) on every request.
 * Only the metadata-relevant slice is constructed now; everything else
 * resolves lazily through the base.runtime locator (see BaseBundle::boot()
 * and BaseCommonTrait::runtimeGet()).
 */
class EagerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected AnnotationReader $annotationReader,
        protected ClassMetadataManipulator $classMetadataManipulator,
        protected LocalizerInterface $localizer
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onValidCache', 128]], # must be called IntegritySubscriber::onKernelRequest()
            ConsoleEvents::COMMAND => ['onCommand', 2049]
        ];
    }

    public function onCommand()
    {
        // Dependencies got constructed by DI before the first metadata load..
    }

    public function onValidCache(KernelEvent $e)
    {
        BaseBundle::getInstance()->markCacheAsValid();
    }
}
