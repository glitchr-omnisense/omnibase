<?php

namespace Base\Subscriber;

use Base\Controller\BaseController;
use Base\Service\BaseService;
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
        $this->autoAppend = $this->baseService->getParameterBag("base.twig.autoappend");
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onConsoleCommand'],
            KernelEvents::REQUEST  => ['onKernelRequest'],
            KernelEvents::RESPONSE => ['onKernelResponse'],
        ];
    }

    // Make sure base service is called eagerly very in the early stage 
    public function onConsoleCommand() { return null; } 
    
    public function onKernelRequest(RequestEvent $event)
    {
        BaseController::$foundBaseSubscriber = true;

        $this->baseService->addHtmlContent("javascripts", $this->baseService->getAsset($this->baseService->getParameterBag("base.vendor.jquery-ui.css")));
        $this->baseService->addHtmlContent("stylesheets", $this->baseService->getAsset("bundles/base/app.css"));

        $this->baseService->addHtmlContent("javascripts", $this->baseService->getAsset($this->baseService->getParameterBag("base.vendor.jquery.js")));
        $this->baseService->addHtmlContent("javascripts", $this->baseService->getAsset($this->baseService->getParameterBag("base.vendor.jquery-ui.js")));
        $this->baseService->addHtmlContent("javascripts", $this->baseService->getAsset("bundles/base/app.js"));

        if($this->baseService->isProfiler($event) && !$this->baseService->isDebug())
            throw new NotFoundHttpException("Page not found.");
    }

    private function allowRender(ResponseEvent $event)
    {
        if (!$this->autoAppend)
            return false;

        $contentType = $event->getResponse()->headers->get('content-type');
        if ($contentType && !str_contains($contentType, "text/html"))
            return false;
    
        if (!$event->isMainRequest())
            return false;
        
        return true;
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        $ret = false;
        if ($this->baseService->isDebug()) {

            $request = $event->getRequest();
            if ($request->isXmlHttpRequest()) {

                $response = $event->getResponse();
                $response->headers->set('Symfony-Debug-Toolbar-Replace', true);

                $ret = true;
            }
        }

        if ($this->allowRender($event)) {

            $response = $event->getResponse();
            $content = $response->getContent();

            $noscripts   = $this->baseService->getHtmlContent("noscripts");
            $content = preg_replace('/<body\b[^>]*>/', "$0".$noscripts, $content, 1);

            $stylesheets = $this->baseService->getHtmlContent("stylesheets");
            $content = preg_replace('/<\/head\b[^>]*>/', $stylesheets."$0", $content, 1);

            $javascripts = $this->baseService->getHtmlContent("javascripts");
            $content = preg_replace('/<\/head\b[^>]*>/', $javascripts."$0", $content, 1);

            $javascriptsHead = $this->baseService->getHtmlContent("javascripts:head");
            $content = preg_replace('/<\/head\b[^>]*>/', $javascriptsHead."$0", $content, 1);

            $javascriptsBody = $this->baseService->getHtmlContent("javascripts:body");
            $content = preg_replace('/<\/body\b[^>]*>/', "$0".$javascriptsBody, $content, 1);

            $response->setContent($content);
            $ret = true;
        }

        return $ret;
    }
}
