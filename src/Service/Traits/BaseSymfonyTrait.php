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

    public function addSession($name, $value)
    {
        $this->getSession()->set($name, $value);
    }

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
    
    public function generateUrl(string $name = "", array $opts = []) { return $this->getPath($name, $opts); }
    public function getCurrentPath() { return $this->getPath(); }
    public function getPath(string $name = "", array $opts = [])
    {
        if (!empty($name)) {
            try {
                return BaseService::$router->generate( $name, $opts);
            } catch (RouteNotFoundException $e) {
                return null;
            }
        }

        return ($request = $this->getRequest()) ? $request->get('route') : null;
    }

    public function getCurrentPathName() { return $this->getPathName(); }
    public function getPathName($path = "")
    {
        if (empty($path)) $path = $_SERVER["REQUEST_URI"];
        $path = parse_url($path, PHP_URL_PATH);

        try {
            return $this->getPathr()->match($path)['_route'];
        } catch (ResourceNotFoundException $e) {
            return null;
        }
    }

    public function redirect(string $url, int $state = 302): RedirectResponse { return new RedirectResponse($url, $state); }
    public function redirectToRoute($event, string $route, $exceptionPattern = null, $callback = null)
    {
        $route     = $this->getPath($route) ?? $route;
        $routeName = $this->getPathName($route) ?? $route;

        $currentRouteName = $this->getPathName();
        if ($currentRouteName == $routeName)
            return false;

        if($exceptionPattern) {
        
            if(is_string($exceptionPattern))
                $exceptionPattern = [$exceptionPattern];

            foreach($exceptionPattern as $pattern) {
                
                if (preg_match($pattern, $currentRouteName))
                    return false;
            }
        }

        if(is_callable($callback)) $callback();
        $event->setResponse(new RedirectResponse($route));
        return true;
    }

    public function getRequest()
    {
        if (!$this->rstack) return null;
        return $this->rstack->getCurrentRequest();
    }

    public function getEnvironment(): string
    {
        if (!isset($this->environment))
            throw new Exception("Symfony environment not found in BaseService. Did you overloaded BaseService::__construct ?");

        return $this->environment;
    }

    public function isProduction() { return $this->kernel->getEnvironment() == "prod"; }
    public function isDevelopment() { return $this->kernel->getEnvironment() == "dev"; }
    public function isDebug() { return $this->kernel->isDebug(); }

    public function isMaintenance() { return file_exists($this->getParameterBag("base.maintenance.lockpath")); }

    public function getDuration(): float { return round(microtime(true) - self::$startTime, 2); }

    public function duration() { return $this->getDuration(); }
    public function age() { return $this->getAge(); }
    public function birthdate() { $this->getBirthdate(); }
    public function protocol() { return $this->getProtocol(); }
    public function domain(int $level = 0) { return $this->getDomain($level); }
    public function www() { return $this->getWebsite(); }
    public function url() { return $this->getWebsite(); }
    public function assets() { return $this->getAssets(); }
    public function vendor() { return $this->getVendor(); }

    public function getBirthdate():string { return ($this->getParameterBag('base.birthdate') < 0) ? date("Y") : $this->getParameterBag('base.birthdate'); }
    public function getProtocol(): string { return ($this->getParameterBag('base.use_https') ? "https" : "http"); }
    public function getDomain(int $level = 0)  : string 
    {
        $domain = $this->getParameterBag("base.domain");
        while($level-- > 0)
            $domain = preg_replace("/^(\w+)./i", "", $domain);
        
        return $domain;
    }
    public function getAge():string
    {
        $birthdate = $this->getBirthdate();
        return (date("Y") == $birthdate) ? date("Y") : date("$birthdate-Y");
    }

    public function getWebsite()    { return $this->getProtocol() . "://" . $this->getDomain(); }
    public function getAssets()     { return $this->getProtocol() . "://" . $this->getParameterBag('base.assets'); }
    public function getVendor()     { return $this->getAssets(false) . "/vendor"; }
    public function getServerName() { return $_SERVER["SERVER_NAME"] ?? $this->getDomain(); }
    public function getSubdomain()
    {
        $serverName = $this->getServerName();
        $domain     = $this->getDomain();

        if( ($subdomain = preg_replace("/.".$domain."$/", "", $serverName)) ) {

            if($serverName == $subdomain) return "";
            return $subdomain;
        }

        return "";
    }
}
