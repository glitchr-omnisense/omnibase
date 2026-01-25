<?php

namespace Base\Subscriber;

use Base\BaseBundle;
use Base\Service\BaseService;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;

class EagerSubscriber implements EventSubscriberInterface
{
    /**
     * @var BaseService
     */
    protected BaseService $baseService;

    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
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
        // Make sure base service is loaded very early.. 
    }

    public function onValidCache(KernelEvent $e)
    {
        BaseBundle::getInstance()->markCacheAsValid();
    }
}
