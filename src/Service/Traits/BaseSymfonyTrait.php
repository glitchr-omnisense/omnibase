<?php

namespace Base\Service\Traits;

use Base\Entity\Thread;
use Base\Entity\User;
use Base\Service\BaseService;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

use Symfony\Component\Config\Definition\Exception\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
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

    public function getRepository(string $className, bool $reopen = false) 
    { 
        return $this->getEntityManager($reopen)->getRepository($className); 
    }

    public function isWithinDoctrine()
    {
        $debug_backtrace = debug_backtrace();
        foreach($debug_backtrace as $trace)
            if(str_starts_with($trace["class"], "Doctrine")) return true;

        return false;
    }

    public function getOriginalEntityData($eventOrEntity, bool $reopen = false)
    { 
        $entity = $eventOrEntity->getObject();
        $originalEntityData = $this->getEntityManager($reopen)->getUnitOfWork()->getOriginalEntityData($entity);

        if($eventOrEntity instanceof PreUpdateEventArgs) {

            $event = $eventOrEntity;
            foreach($event->getEntityChangeSet() as $field => $data)
                $originalEntityData[$field] = $data[0];

        } else if($this->isWithinDoctrine()) {

            dump("Achtung ! You are trying to access data object within a Doctrine method..".
                        "Original entity might have already been updated.");
            return null;
        }

        return $originalEntityData;
    }

    protected static $entitySerializer = null;
    public function getOriginalEntity($eventOrEntity, bool $reopen = false)
    { 
        if (!self::$entitySerializer)
            self::$entitySerializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);

        $data = $this->getOriginalEntityData($eventOrEntity, $reopen);

        if(!$eventOrEntity instanceof LifecycleEventArgs) $entity = $eventOrEntity;
        else $entity = $eventOrEntity->getObject();

        return self::$entitySerializer->deserialize(json_encode($data), get_class($entity), 'json');
    }

    public function getEntityById(string $className, int $id, bool $reopen = false)
    {
        $repository = $this->getRepository($className, $reopen);
        return $repository->findOneBy(["id" => $id]) ?? null;
    }

    public static function getEntityManager(bool $reopen = false): ?EntityManagerInterface
    {
        if (!BaseService::$entityManager) return null;
        if (!BaseService::$entityManager->isOpen()) {

            if(!$reopen) return null;
            BaseService::$entityManager = BaseService::$entityManager->create(
                BaseService::$entityManager->getConnection(), BaseService::$entityManager->getConfiguration());
        }

        return BaseService::$entityManager;
    }
    
    public function getRouteWithUrl(string $path = "", array $opts = []) { return $this->getWebsite() . $this->getRoute($path, $opts); }
    public function     generateUrl(string $path = "", array $opts = []) { return $this->getRoute($path, $opts); }
    public function getCurrentRoute() { return $this->getRoute(); }
    public function getRoute(string $path = "", array $opts = [])
    {
        if (!empty($path)) {
            try {
                return BaseService::$router->generate( $path, $opts);
            } catch (RouteNotFoundException $e) {
                return null;
            }
        }

        return ($request = $this->getRequest()) ? $request->get('route') : null;
    }

    public function getCurrentRouteName() { return $this->getRouteName(); }
    public function getRouteName($url = "")
    {
        if (empty($url)) $url = $_SERVER["REQUEST_URI"];
        $path = parse_url($url, PHP_URL_PATH);

        try {
            return $this->getRouter()->match($path)['_route'];
        } catch (ResourceNotFoundException $e) {
            return null;
        }
    }

    public function redirect(string $url, int $state = 302): RedirectResponse { return new RedirectResponse($url, $state); }
    public function redirectToRoute($event, string $route, string $exceptionPattern = "/^$/", $callback = null)
    {
        $route     = $this->getRoute($route) ?? $route;
        $routeName = $this->getRouteName($route) ?? $route;
        $currentRouteName = $this->getRouteName();

        if ($currentRouteName == $routeName)
            return false;

        if (preg_match($exceptionPattern, $currentRouteName))
            return false;

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
