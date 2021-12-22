<?php

namespace Base\Service\Traits;

use Base\Service\BaseService;
use Base\Service\ParameterBagInterface;
use Base\Twig\Extension\BaseTwigExtension;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

use Symfony\Component\Config\Definition\Exception\Exception;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Contracts\EventDispatcher\Event;

trait BaseSymfonyTrait
{
    private static $startTime = 0;
    public function getExecutionTime(): float { return round(microtime(true) - self::$startTime, 2); }
    public function execution_time() { return $this->getExecutionTime(); }
    public function setStartTime()
    {
        // Provide the kernel start time as time reference
        self::$startTime = $this->kernel->getStartTime();
        if (is_infinite(self::$startTime)) self::$startTime = microtime(true);
    }

    public function hasPost()    { return isset($_POST); }
    public function hasGet()     { return isset($_GET); }
    public function hasSession() { return isset($_SESSION); }
    public function addSession($name, $value) { $this->getSession()->set($name, $value); }
    public function removeSession($name) { return ($this->requestStack && $this->requestStack->getSession()->has($name)) ? $this->requestStack->getSession()->remove($name) : null; }
    public function getSession($name = null)
    {
        if(!$name) return $this->requestStack->getSession();
        return ($this->requestStack && $this->requestStack->getSession()->has($name)) ? $this->requestStack->getSession()->get($name) : null;
    }

    public function createForm($type, $data = null, array $options = []): FormInterface { return $this->formFactory->create($type, $data, $options); }

    public function getLocale(?string $locale = null) { return BaseService::getLocaleProvider()->getLocale($locale); }

    public function getProfiler() { return $this->kernel->getContainer()->get('profiler'); }
    public function getProfile($response = null)
    {
        if (!$response) return null;
        return $this->getProfiler()->loadProfileFromResponse($response);
    }
    
    public function getRequest(): ?Request { return $this->getCurrentRequest(); }
    public function getCurrentRequest(): ?Request { return $this->requestStack ? $this->requestStack->getCurrentRequest() : null; }

    public function getParameter(string $name): array|bool|string|int|float|null { return $this->kernel->getContainer()->getParameter($name); }
    public function hasParameter(string $name): bool { return $this->kernel->getContainer()->hasParameter($name); }
    public function setParameter(string $name, array|bool|string|int|float|null $value) { return $this->kernel->getContainer()->setParameter($name, $value); }

    public function getParameterBag(string $key = "", array $bag = null) { return !empty($key) ? self::$parameterBag->get($key, $bag) : self::$parameterBag; }

    public function generateUrl(string $route = "", array $opts = []): ?string { return $this->getUrl($route, $opts); }
    public function getCurrentUrl(): ?string { return $this->getUrl(); }
    public function getUrl(?string $route = "", array $opts = []): ?string
    {
        if (!empty($route)) {

            try { return BaseService::$router->generate($route, $opts); }
            catch (RouteNotFoundException $e) { return $route; }
        }

        return ( ($request = $this->getRequest()) ? BaseService::$router->generate($request->get('_route')) : null);
    }

    public function getCurrentRoute(): ?string {
        
        $request = $this->getRequest();
        if(!$request) return null;

        return $this->getRoute($request->getRequestUri());
    }

    public function getRoute(?string $url): ?string
    {
        if(!$url) return null;

        $baseDir = $this->getAsset("/");
        $path = parse_url($url, PHP_URL_PATH);
        if ($baseDir && strpos($path, $baseDir) === 0)
            $path = substr($path, strlen($baseDir));

        try { return $this->getRouter()->match($path)['_route']; }
        catch (ResourceNotFoundException $e) { return null; }
    }

    public function redirect(string $urlOrRoute, array $opts = [], int $state = 302, array $headers = []): RedirectResponse { return new RedirectResponse($this->getUrl($urlOrRoute, $opts), $state, $headers); }
    public function redirectToRoute(string $route, array $opts = [], int $state = 302, array $headers = []): ?RedirectResponse
    {
        $event = null;
        if(array_key_exists("event", $headers)) {
            $event = $headers["event"];
            if(! ($event instanceof Event) ) 
                throw new InvalidArgumentException("header variable \"event\" must be ".Event::class.", currently: ".(is_object($event) ? get_class($event) : gettype($event)));
            unset($headers["event"]);
        }

        $exceptions = [];
        if(array_key_exists("exceptions", $headers)) {
            $exceptions = $headers["exceptions"];
            if(!is_string($exceptions) && !is_array($exceptions)) 
                throw new InvalidArgumentException("header variable \"exceptions\" must be of type \"array\" or \"string\", currently: ".(is_object($exceptions) ? get_class($exceptions) : gettype($exceptions)));
            unset($headers["exceptions"]);
        }
        
        $callback = null;
        if(array_key_exists("callback", $headers)) {
            $callback = $headers["callback"];
            if(!is_callable($callback)) 
                throw new InvalidArgumentException("header variable \"callback\" must be callable, currently: ".(is_object($callback) ? get_class($callback) : gettype($callback)));
            unset($headers["callback"]);
        }
        
        $url   = $this->getUrl($route, $opts) ?? $route;
        $route = $this->getRoute($url); // Normalize and check if route exists
        if (!$route) return null;

        $currentRoute = $this->getCurrentRoute();
        if ($route == $currentRoute) return null;

        $exceptions = is_string($exceptions) ? [$exceptions] : $exceptions;
        foreach($exceptions as $pattern) 
            if (preg_match($pattern, $currentRoute)) return null;

        $response = new RedirectResponse($url, $state, $headers);
        if($event && method_exists($event, "setResponse")) $event->setResponse($response);

        // Callable action if redirection happens
        if(is_callable($callback)) $callback();

        return $response;
    }

    public function refresh(?Request $request = null): RedirectResponse 
    {
        $request = $request ?? $this->getRequest();
        return $this->redirect($request->get('_route'));
    }

    public function isMaintenance() { return $this->getSettings()->maintenance() || file_exists($this->getParameterBag("base.maintenance.lockpath")); }
    public function isDevelopment() { return $this->kernel->getEnvironment() == "dev"; }
    public function isProduction()  { return $this->kernel->getEnvironment() != "dev"; }

    public function isCli() { return is_cli(); }
    public function isDebug() { return $this->kernel->isDebug(); }
    public function isProfiler($request = null)
    {
        if(!$request) $request = $this->getRequest();
        if($request instanceof KernelEvent)
            $request = $request->getRequest();
        else if($request instanceof RequestStack)
            $request = $request->getCurrentRequest();
        else if(!$request instanceof Request)
            throw new \InvalidArgumentException("Invalid argument provided, expected either RequestStack or Request");

        $route = $request->get('_route');

        return $route == "_wdt" || $route == "_profiler";
    }

    public function isEasyAdmin($request = null)
    {
        if(!$request) $request = $this->getRequest();
        if($request instanceof KernelEvent)
            $request = $request->getRequest();
        else if($request instanceof RequestStack)
            $request = $request->getCurrentRequest();
        else if(!$request instanceof Request)
            throw new \InvalidArgumentException("Invalid argument provided, expected either RequestStack or Request");

        $controllerAttribute = $request->attributes->get("_controller");
        $array = is_array($controllerAttribute) ? $controllerAttribute : explode("::", $request->attributes->get("_controller"));
        $controller = explode("::", $array[0])[0];

        $parents = [];

        $parent = $controller;
        while(class_exists($parent) && ( $parent = get_parent_class($parent)))
            $parents[] = $parent;

        $eaParents = array_filter($parents, fn($c) => str_starts_with($c, "EasyCorp\Bundle\EasyAdminBundle"));
        return !empty($eaParents);
    }
}
