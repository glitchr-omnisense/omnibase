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
    protected $baseService;
    
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => [['onKernelRequest', 2048], ['onValidCache', 8]],
            ConsoleEvents::COMMAND => ['onCommand', 2048]
        ];
    }

    public function onCommand() { }
    public function onValidCache(KernelEvent $e) { BaseBundle::markCacheAsValid(); }
    public function onKernelRequest(KernelEvent $e)
    {
        if($e->getRequest()->getPathInfo() == "/") return;
        if(!$this->baseService->getCurrentRouteName()) return;
        if(str_starts_with($this->baseService->getCurrentRouteName(), "_")) return;

        if(!BaseBundle::hasDoctrine()) {

            $e->setResponse($this->baseService->redirect($this->baseService->getRouteName("/")));
            $e->stopPropagation();
        }
    }
}