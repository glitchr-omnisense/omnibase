<?php

namespace Base\Service\Traits;

use Base\Entity\Thread;
use Base\Entity\User;
use Base\Service\BaseService;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

use Symfony\Component\Config\Definition\Exception\Exception;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

trait BaseSymfonyTrait
{
    private static $startTime = 0;

    public function setStartTime()
    {
        // Provide the kernel start time as time reference
        self::$startTime = $this->kernel->getStartTime();
        if (is_infinite(self::$startTime)) self::$startTime = microtime(true);
    }

    public function hasPost() { return isset($_POST); }
    public function hasGet() { return isset($_GET); }
    public function hasSession() { return isset($_SESSION); }

    public function addSession($name, $value) { $this->getSession()->set($name, $value); }
    public function getSession($name = null)
    {
        if(!$name) return $this->rstack->getSession();

        return ($this->rstack && $this->rstack->getSession()->has($name)) ? $this->rstack->getSession()->get($name) : null;
    }

    public function removeSession($name)
    {
        return ($this->rstack && $this->rstack->getSession()->has($name)) ? $this->rstack->getSession()->remove($name) : null;
    }

    public function createForm($type, $data = null, array $options = []): FormInterface
    {
        return $this->formFactory->create($type, $data, $options);
    }

    public function getAvailableServices(): array
    {
        if (!isset($this->container))
            throw new Exception("Symfony container not found in BaseService. Did you overloaded BaseService::__construct ?");

        return $this->container->getServiceIds();
    }

    public function getContainer($name) { return ($name ? $this->container->get($name) : $this->container); }

    public function getParameterBag(string $key = "", array $bag = null)
    {
        // NB: Container::getParameter() pick into Container::parameterBag
        if (!isset($this->container))
            throw new Exception("Symfony container not found in BaseService. Did you overloaded BaseService::__construct ?");

        // Return parameter bag in case no key
        if (empty($key))
            return $this->container->getParameterBag()->all();

        // Simple parameter stored
        if ($this->container->hasParameter($key))
            return $this->container->getParameter($key);

        // Array parameter stored
        $array = [];
        for ($i = 0; $this->container->hasParameter($key . "." . $i); $i++)
            $array[] = $this->container->getParameter($key . "." . $i);

        if (!empty($array)) return $array;

        // Associative array stored
        if ($bag == null) $bag = $this->container->getParameterBag()->all();
        if (($paths = preg_grep('/' . $key . '\.[0-9]*\.[.*]*/', array_keys($bag)))) {

            foreach ($paths as $path)
                $this->setParameterBag($array, $path, $bag[$path]);

            foreach (explode(".", $key) as $key)
                $array = &$array[$key];

            return $array;
        }

        return null;
    }

    function setParameterBag(&$arr, $path, $value, $separator = '.')
    {
        $keys = explode($separator, $path);

        foreach ($keys as $key) {
            $arr = &$arr[$key];
        }

        $arr = $value;
    }

    public function getProfiler() { return $this->kernel->getContainer()->get('profiler'); }
    public function getProfile($response = null)
    {
        if (!$response) return null;
        return $this->getProfiler()->loadProfileFromResponse($response);
    }
    
    public function getCurrentRequest(): ?Request { return $this->getCurrentRequest(); }
    public function getRequest(): ?Request
    {
        if (!$this->rstack) return null;
        return $this->rstack->getCurrentRequest();
    }

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
        if (strpos($path, $baseDir) === 0)
            $path = substr($path, strlen($baseDir));
        
        try { return $this->getRouter()->match($path)['_route']; }
        catch (ResourceNotFoundException $e) { return null; }
    }

    public function redirect(string $urlOrRoute, array $opts = [], int $state = 302, array $headers = []): RedirectResponse { return new RedirectResponse($this->getUrl($urlOrRoute, $opts), $state, $headers); }
    public function redirectToRoute(string $route, array $opts = [], $event = null, $exceptionPattern = null, $callback = null): ?RedirectResponse
    {
        $url   = $this->getUrl($route, $opts) ?? $route;
        $route = $this->getRoute($url); // Normalize and check if route exists
        if (!$route) return null;

        $currentRoute = $this->getCurrentRoute();
        if ($route == $currentRoute) return null;

        if($exceptionPattern) {

            if(is_string($exceptionPattern))
                $exceptionPattern = [$exceptionPattern];

            foreach($exceptionPattern as $pattern) {

                if (preg_match($pattern, $currentRoute))
                    return null;
            }
        }
        
        $response = new RedirectResponse($url);
        if($event) $event->setResponse($response);

        // Callable action if redirection happens
        if(is_callable($callback)) $callback();

        return $response;
    }

    public function refresh(?Request $request = null): RedirectResponse 
    { 
        $request = $request ?? $this->getRequest();
        return $this->redirect($request->get('_route'));
    }

    public function isMaintenance() { return $this->getSettings("base.settings.maintenance") || file_exists($this->getParameterBag("base.maintenance.lockpath")); }
    public function isProduction() { return $this->kernel->getEnvironment() == "prod"; }
    public function isDevelopment() { return $this->kernel->getEnvironment() == "dev"; }
    public function isDebug() { return $this->kernel->isDebug(); }

    public function getExecutionTime(): float { return round(microtime(true) - self::$startTime, 2); }
    public function execution_time() { return $this->getExecutionTime(); }

    public function getSettings(?string $name = null) { return (!$name ? BaseService::$settings : BaseService::$settings->get($name)); }
    public function settings(?string $name = null) { return $this->getSettings($name); }
}
