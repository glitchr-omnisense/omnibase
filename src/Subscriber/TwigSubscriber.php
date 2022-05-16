<?php

namespace Base\Subscriber;

use Base\Service\BaseService;
use InvalidArgumentException;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TwigSubscriber implements EventSubscriberInterface
{
    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
        $this->autoAppend  = $this->baseService->getParameterBag("base.twig.autoappend");
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 128],
            KernelEvents::RESPONSE => ['onKernelResponse'],
        ];
    }

    private function allowRender(ResponseEvent $event)
    {
        if (!$this->autoAppend)
            return false;

        $contentType = $event->getResponse()->headers->get('content-type');
        if ($contentType && !str_contains($contentType, "text/html"))
            return false;
    
        if ($this->baseService->isProfiler())
            return false;

        if (!$event->isMainRequest())
            return false;
        
        return true;
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
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        if ($this->allowRender($event)) {

            $response = $event->getResponse();
            $content = $response->getContent();

            $noscripts   = $this->baseService->getHtmlContent("noscripts");
            $content = preg_replace('/<body\b[^>]*>/', "$0".$noscripts, $content, 1);

            $stylesheetsHead = $this->baseService->getHtmlContent("stylesheets:head");
            $content = preg_replace('/(head\b[^>]*>)(.*?)(<link|<style)/s', "$1$2".$stylesheetsHead."$3", $content, 1);

            $stylesheets = $this->baseService->getHtmlContent("stylesheets");
            $content = preg_replace('/<\/head\b[^>]*>/', $stylesheets."$0", $content, 1);

            $javascriptsHead = $this->baseService->getHtmlContent("javascripts:head");
            $content = preg_replace('/(head\b[^>]*>)(.*?)(<script)/s', "$1$2".$javascriptsHead."$3", $content, 1);

            $javascripts = $this->baseService->getHtmlContent("javascripts");
            $content = preg_replace('/<\/head\b[^>]*>/', $javascripts."$0", $content, 1);

            $javascriptsBody = $this->baseService->getHtmlContent("javascripts:body");
            $content = preg_replace('/<\/body\b[^>]*>/', "$0".$javascriptsBody, $content, 1);

            if(!$response instanceof StreamedResponse)
                $response->setContent($content);

            return true;
        }

        return false;
    }
}
