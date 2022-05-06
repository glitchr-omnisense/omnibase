<?php

namespace Base\Subscriber;

use Base\Service\BaseService;
use InvalidArgumentException;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BaseSubscriber implements EventSubscriberInterface
{
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onKernelRequest', 128],
            KernelEvents::RESPONSE => ['onKernelResponse'],
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $this->baseService->addHtmlContent("stylesheets:head", $this->baseService->getAsset($this->baseService->getParameterBag("base.vendor.jquery-ui.stylesheet")));
        $this->baseService->addHtmlContent("stylesheets", $this->baseService->getAsset($this->baseService->getParameterBag("base.vendor.lightbox.stylesheet")));
        $this->baseService->addHtmlContent("stylesheets", $this->baseService->getAsset($this->baseService->getParameterBag("base.vendor.clipboardjs.stylesheet")));
        $this->baseService->addHtmlContent("stylesheets", $this->baseService->getAsset("bundles/base/app.css"));

        $this->baseService->addHtmlContent("javascripts:head", $this->baseService->getAsset($this->baseService->getParameterBag("base.vendor.jquery.javascript")));
        $this->baseService->addHtmlContent("javascripts:head", $this->baseService->getAsset($this->baseService->getParameterBag("base.vendor.jquery-ui.javascript")));
        $this->baseService->addHtmlContent("javascripts", $this->baseService->getAsset($this->baseService->getParameterBag("base.vendor.lightbox.javascript")));
        $this->baseService->addHtmlContent("javascripts", $this->baseService->getAsset($this->baseService->getParameterBag("base.vendor.lightbox2b.javascript")));
        $this->baseService->addHtmlContent("javascripts", $this->baseService->getAsset($this->baseService->getParameterBag("base.vendor.cookie-consent.javascript")));
        $this->baseService->addHtmlContent("javascripts", $this->baseService->getAsset($this->baseService->getParameterBag("base.vendor.clipboardjs.javascript")));
        $this->baseService->addHtmlContent("javascripts", $this->baseService->getAsset("bundles/base/app.js"));

        if($this->baseService->isProfiler($event) && !$this->baseService->isDebug())
            throw new NotFoundHttpException("Page not found.");
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        if ($this->baseService->isDebug()) {

            $request = $event->getRequest();
            if ($request->isXmlHttpRequest()) {

                $response = $event->getResponse();
                $response->headers->set('Symfony-Debug-Toolbar-Replace', true);

                return true;
            }
        }

        return false;
    }
}
