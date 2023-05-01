<?php

namespace Base\Subscriber;

use App\Enum\UserRole;
use Base\Routing\RouterInterface;
use Base\Service\ParameterBag;
use Base\Twig\Renderer\Adapter\EncoreTagRenderer;
use Base\Twig\Renderer\Adapter\HtmlTagRenderer;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 *
 */
class TwigSubscriber implements EventSubscriberInterface
{
    /**
     * @var HtmlRagRenderer
     */
    protected $htmlTagRenderer;

    /**
     * @var EncoreTagRenderer
     */
    protected $encoreTagRenderer;

    /**
     * @var ParameterBag
     */
    protected $parameterBag;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var AuthorizationChecker
     */
    protected $authorizationChecker;

    /** * @var string */
    protected string $publicDir;
    /** * @var bool */
    protected ?bool $autoAppend;

    public function __construct(HtmlTagRenderer $htmlTagRenderer, EncoreTagRenderer $encoreTagRenderer, AuthorizationCheckerInterface $authorizationChecker, ParameterBag $parameterBag, RouterInterface $router, string $publicDir)
    {
        $this->encoreTagRenderer = $encoreTagRenderer;

        $this->htmlTagRenderer = $htmlTagRenderer;

        $this->parameterBag = $parameterBag;
        $this->router = $router;
        $this->authorizationChecker = $authorizationChecker;

        $this->publicDir = $publicDir;
        $this->autoAppend = $this->parameterBag->get("base.twig.autoappend");
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // ConsoleEvents::COMMAND => ['onConsoleCommand'],
            KernelEvents::REQUEST => ['onKernelRequest', 8],
            KernelEvents::RESPONSE => ['onKernelResponse'],
            KernelEvents::EXCEPTION => ['onKernelException'],
        ];
    }

    /**
     * @param ResponseEvent $event
     * @return bool
     */
    private function allowRender(ResponseEvent $event)
    {
        if (!$this->autoAppend) {
            return false;
        }

        $contentType = $event->getResponse()->headers->get('content-type');
        if ($contentType && !str_contains($contentType, "text/html")) {
            return false;
        }

        if ($this->router->isProfiler()) {
            return false;
        }

        if ($this->exceptionTriggered) {
            return false;
        }

        if (!$event->isMainRequest()) {
            return false;
        }

        return true;
    }

    protected $exceptionTriggered = false;

    public function onKernelException(RequestEvent $event)
    {
        $this->exceptionTriggered = true;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        //
        // Permission based entries
        foreach (UserRole::getPermittedValues() as $role) {
            $tag = "security-" . strtolower(str_lstrip($role, "ROLE_"));
            if (!$this->encoreTagRenderer->hasEntry($tag)) {
                continue;
            }

            if ($this->authorizationChecker->isGranted($role)) {
                $this->encoreTagRenderer->addTag($tag);
            }
        }

        //
        // Breakpoint based entries
        foreach ($this->parameterBag->get("base.twig.breakpoints") ?? [] as $breakpoint) {
            $this->encoreTagRenderer->addBreakpoint($breakpoint["name"], $breakpoint["media"] ?? "all");
        }

        //
        // Alternative entries
        $this->encoreTagRenderer->addAlternative("async");
        $this->encoreTagRenderer->addAlternative("defer");
    }

    /**
     * @param ResponseEvent $event
     * @return bool
     * @throws \Exception
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        $allowRender = $this->allowRender($event);
        if (!$allowRender) {
            return false;
        }

        $response = $event->getResponse();
        if (is_instanceof($response, [StreamedResponse::class, BinaryFileResponse::class])) {
            return false;
        }

        // Encore rest rendering
        $response = $this->encoreTagRenderer->renderFallback($response);

        // Html rest rendering
        $response = $this->htmlTagRenderer->renderFallback($response);

        return true;
    }
}
