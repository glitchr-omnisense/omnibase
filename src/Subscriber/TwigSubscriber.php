<?php

namespace Base\Subscriber;

use Base\Routing\RouterInterface;
use Base\Service\ParameterBag;
use Base\Twig\Environment;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class TwigSubscriber implements EventSubscriberInterface
{
    public function __construct(Environment $twig, ParameterBag $parameterBag, RouterInterface $router)
    {
        $this->twig         = $twig;
        $this->parameterBag = $parameterBag;
        $this->router       = $router;

        $this->autoAppend   = $this->parameterBag->get("base.twig.autoappend");
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

        if ($this->router->isProfiler())
            return false;

        if (!$event->isMainRequest())
            return false;

        return true;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $this->twig->addHtmlContent("stylesheets:head", $this->twig->getAsset($this->parameterBag->get("base.vendor.jquery-ui.stylesheet")));
        $this->twig->addHtmlContent("stylesheets", $this->twig->getAsset($this->parameterBag->get("base.vendor.lightbox.stylesheet")));
        $this->twig->addHtmlContent("stylesheets", $this->twig->getAsset($this->parameterBag->get("base.vendor.clipboardjs.stylesheet")));
        $this->twig->addHtmlContent("stylesheets", $this->twig->getAsset($this->parameterBag->get("base.vendor.dockjs.stylesheet")));
        $this->twig->addHtmlContent("stylesheets", $this->twig->getAsset("bundles/base/app.css"));

        $this->twig->addHtmlContent("javascripts:head", $this->twig->getAsset($this->parameterBag->get("base.vendor.jquery.javascript")));
        $this->twig->addHtmlContent("javascripts:head", $this->twig->getAsset($this->parameterBag->get("base.vendor.jquery-ui.javascript")));
        $this->twig->addHtmlContent("javascripts", $this->twig->getAsset($this->parameterBag->get("base.vendor.lightbox.javascript")));
        $this->twig->addHtmlContent("javascripts", $this->twig->getAsset($this->parameterBag->get("base.vendor.lightbox2b.javascript")));
        $this->twig->addHtmlContent("javascripts", $this->twig->getAsset($this->parameterBag->get("base.vendor.cookie-consent.javascript")));
        $this->twig->addHtmlContent("javascripts", $this->twig->getAsset($this->parameterBag->get("base.vendor.clipboardjs.javascript")));
        $this->twig->addHtmlContent("javascripts", $this->twig->getAsset($this->parameterBag->get("base.vendor.dockjs.javascript")));
        $this->twig->addHtmlContent("javascripts", $this->twig->getAsset("bundles/base/app.js"));
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        if ($this->allowRender($event)) {

            $response = $event->getResponse();
            $content = $response->getContent();

            $noscripts   = $this->twig->getHtmlContent("noscripts");
            $content = preg_replace('/<body\b[^>]*>/', "$0".$noscripts, $content, 1);

            $stylesheetsHead = $this->twig->getHtmlContent("stylesheets:head");
            $content = preg_replace('/(head\b[^>]*>)(.*?)(<link|<style)/s', "$1$2".$stylesheetsHead."$3", $content, 1);

            $stylesheets = $this->twig->getHtmlContent("stylesheets");
            $content = preg_replace('/<\/head\b[^>]*>/', $stylesheets."$0", $content, 1);

            $javascriptsHead = $this->twig->getHtmlContent("javascripts:head");
            $content = preg_replace('/(head\b[^>]*>)(.*?)(<script)/s', "$1$2".$javascriptsHead."$3", $content, 1);

            $javascripts = $this->twig->getHtmlContent("javascripts");
            $content = preg_replace('/<\/head\b[^>]*>/', $javascripts."$0", $content, 1);

            $javascriptsBody = $this->twig->getHtmlContent("javascripts:body");
            $content = preg_replace('/<\/body\b[^>]*>/', "$0".$javascriptsBody, $content, 1);

            if(!is_instanceof($response, [StreamedResponse::class, BinaryFileResponse::class]))
                $response->setContent($content);

            return true;
        }

        return false;
    }
}
