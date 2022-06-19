<?php

namespace Base\Subscriber;

use Base\Service\BaseService;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EagerSubscriber implements EventSubscriberInterface
{
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onKernelRequest', 2048],
            ConsoleEvents::COMMAND => ['onCommand', 2048]
        ];
    }

    public function onCommand() { }
    public function onKernelRequest() { }
}