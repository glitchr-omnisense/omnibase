<?php

namespace Base\Service\Traits;

use Base\Database\Factory\ClassMetadataFactory;
use Base\Entity\Thread;
use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\DependencyInjection\Container;

use Symfony\Component\HttpFoundation\RequestStack;

use Base\Entity\User;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

use Symfony\Component\Config\Definition\Exception\Exception;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\String\Slugger\SluggerInterface;

trait BaseSymfonyTrait
{
    private static $startTime = 0;

    private $protocol;
    private $birthdate;
    private $mail;

    public function setSlugger(SluggerInterface $slugger)
    {
        Thread::$slugger = $slugger;
    }

    public function getSlugger()
    {
        return Thread::$slugger;
    }

    public function setRouter(UrlGeneratorInterface $router)
    {
        Thread::$router = $router;
    }

    public function getRouter(): ?UrlGeneratorInterface
    {
        return Thread::$router;
    }

    public function setUserProperty(string $userProperty) {

        User::$property = $userProperty;
        return $this;
    }

    public function setStartTime()
    {
        // Provide the kernel start time as time reference
        self::$startTime = $this->kernel->getStartTime();
        if (is_infinite(self::$startTime)) self::$startTime = microtime(true);
    }

    public function initSymfonyTrait() {

        // Get some parameters from the bag
        $this->birthdate = $this->getParameterBag('base.birthdate');
        $this->protocol  = $this->getParameterBag('base.protocol');
        $this->mail      = $this->getParameterBag('base.mail');
    }

    public function hasPost()
    {
        return isset($_POST);
    }
    public function hasGet()
    {
        return isset($_GET);
    }
    public function hasSession()
    {
        return isset($_SESSION);
    }

    public static $projectDir = null;
    public static function getProjectDir(): ?string
    {
        return self::$projectDir;
    }
    public static function setProjectDir($projectDir)
    {
        return self::$projectDir = $projectDir;
    }
    public function getPublicDir()
    {
        return $this->getProjectDir() . "/public";
    }
    public function getTemplateDir()
    {
        return $this->getProjectDir() . "/templates";
    }
    public function getTranslationDir()
    {
        return $this->getProjectDir() . "/translations";
    }
    public function getCacheDir()
    {
        return $this->getProjectDir() . "/var/cache";
    }
    public function getLogDir()
    {
        return $this->getProjectDir() . "/var/log";
    }
    public function getDataDir()
    {
        return $this->getProjectDir() . "/data";
    }

    public function addSession($name, $value)
    {

        $this->session->set($name, $value);
    }

    public function getSession($name = null)
    {
        if(!$name) return $this->session;

        return ($this->session && $this->session->has($name)) ? $this->session->get($name) : null;
    }

    public function removeSession($name)
    {

        return ($this->session && $this->session->has($name)) ? $this->session->remove($name) : null;
    }



    public function getUser()
    {
        if (!isset($this->security))
            throw new Exception("No security found in BaseService. Did you overloaded BaseService::__construct ?");

        return $this->security->getUser();
    }

    public function isGranted($attribute, $subject = null): bool
    {
        if (!isset($this->security))
            throw new Exception("No authorization checker found in BaseService. Did you overloaded BaseService::__construct ?");


        if ($this->security->getToken() === null) return false;
        return $this->security->isGranted($attribute, $subject);
    }

    public function Logout()
    {
        return $this->container->get("security.token_storage")->setToken(null);
    }

    public function createForm($type, $data = null, array $options = []): FormInterface
    {
        return $this->formFactory->create($type, $data, $options);
    }

    public function isCsrfTokenValid(string $id, ?string $token): bool
    {
        if (!isset($this->csrfTokenManager))
            throw new Exception("No CSRF token manager found in BaseService. Did you overloaded BaseService::__construct ?");

        return $this->csrfTokenManager->isTokenValid(new CsrfToken($id, $token));
    }


    public function getAvailableServices(): array
    {

        if (!isset($this->container))
            throw new Exception("Symfony container not found in BaseService. Did you overloaded BaseService::__construct ?");

        return $this->container->getServiceIds();
    }

    public function getContainer($name)
    {
        return ($name ? $this->container->get($name) : $this->container);
    }

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

    public function getProfiler()
    {
        return $this->kernel->getContainer()->get('profiler');
    }

    public function getProfile($response = null)
    {

        if (!$response) return null;
        return $this->getProfiler()->loadProfileFromResponse($response);
    }

    public function getEntityManager(bool $reopen = false): ?EntityManagerInterface
    {
        if (!isset($this->entityManager))
            throw new Exception("No entity manager found in BaseService. Did you overloaded BaseService::__construct ?");

        if (!$this->entityManager->isOpen()) {

            if(!$reopen) return null;

            $this->entityManager = $this->entityManager->create(
                $this->entityManager->getConnection(),
                $this->entityManager->getConfiguration()
            );
        }

        return $this->entityManager;
    }

    public function getRepository(string $className, bool $reopen = false)
    {
        return $this->getEntityManager($reopen)->getRepository($className);
    }

    public function getOriginalEntityData($entity, bool $reopen = false)
    {
        return $this->getEntityManager($reopen)->getUnitOfWork()->getOriginalEntityData($entity);
    }

    protected static $entitySerializer = null;
    public function getOriginalEntity($entity, bool $reopen)
    {
        if (!self::$entitySerializer)
            self::$entitySerializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);

        $data = $this->getOriginalEntityData($entity, $reopen);
        return self::$entitySerializer->deserialize(json_encode($data), get_class($entity), 'json');
    }

    public function getEntityById(string $className, int $id, bool $reopen = false)
    {
        $repository = $this->getRepository($className, $reopen);
        return $repository->findOneBy(["id" => $id]) ?? null;
    }

    public function getRouteWithUrl($path = "", array $opts = [])
    {
        return $this->getWebsite() . "/" . $this->getRoute($path, $opts);
    }

    public function generateUrl(string $path = "", array $opts = [])
    {
        return $this->getRoute($path, $opts);
    }

    public function getRoute(string $path = "", array $opts = [])
    {
        if (!isset($this->router))
            throw new Exception("No router found in BaseService. Did you overloaded BaseService::__construct ?");

        if (!empty($path)) {
            try {
                return $this->router->generate( $path, $opts);
            } catch (RouteNotFoundException $e) {
                return null;
            }
        }

        return ($request = $this->getRequest()) ? $request->get('route') : null;
    }

    public function getRouteName($url = "")
    {
        if (!isset($this->router))
            throw new Exception("No router found in BaseService. Did you overloaded BaseService::__construct ?");

        if (empty($url)) $url = $_SERVER["REQUEST_URI"];
        $path = parse_url($url, PHP_URL_PATH);

        try {
            return $this->router->match($path)['_route'];
        } catch (ResourceNotFoundException $e) {
            return null;
        }
    }

    public function redirect(string $url, int $state = 302): RedirectResponse
    {
        return new RedirectResponse($url, $state);
    }

    public function redirectToRoute($event, $route, $exceptionPattern = "/^$/")
    {
        $route     = $this->getRoute($route) ?? $route;
        $routeName = $this->getRouteName($route) ?? $route;
        $currentRouteName = $this->getRouteName();

        if ($currentRouteName == $routeName)
            return false;

        if (preg_match($exceptionPattern, $currentRouteName))
            return false;

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


    public function isProduction()
    {
        return $this->kernel->getEnvironment() == "prod";
    }
    public function isDevelopment()
    {
        return $this->kernel->getEnvironment() == "dev";
    }
    public function isDebug()
    {
        return $this->kernel->isDebug();
    }
    public function isMaintenance()
    {
        return file_exists($this->getParameterBag("base.maintenance_lockpath"));
    }

    public function duration()
    {
        return $this->getDuration();
    }
    public function getDuration(): float
    {
        return round(microtime(true) - self::$startTime, 2);
    }

    public function age()
    {
        return $this->getAge();
    }
    public function getAge():string
    {
        return (date("Y") == $this->birthdate) ? date("Y") : date("$this->birthdate-Y");
    }

    public function getProtocol():string
    {
        return $this->protocol;
    }
    public function protocol()
    {
        return $this->getProtocol();
    }

    public function getDomain(): string
    {
        //NB: Cache poisoning attack
        return $this->getParameterBag("base.domain") ?? $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'];
    }

    public function domain()
    {
        return $this->getDomain();
    }

    public function getSubdomain()
    {
        $subdomain = $this->getDomain();
        if( ($subdomain = preg_replace("/.".$subdomain."$/", "", $_SERVER["SERVER_NAME"])) ) {

            if($_SERVER["SERVER_NAME"] == $subdomain) return "";
            return $subdomain;
        }

        return "";
    }

    public function getWebsite()
    {
        $subdomain = $this->getSubdomain();
        $subdomain = (!empty($subdomain) ? $subdomain . "." : "");

        return $this->getProtocol() . "://" . $subdomain . $this->getDomain();
    }

    public function www()
    {
        return $this->getWebsite();
    }

    public function url()
    {
        return $this->getWebsite();
    }

    public function getAssets($subdomain = true)
    {
        if (!$subdomain) $subdomain = "";
        else {
            $subdomain = $this->getSubdomain();
            $subdomain = (!empty($subdomain) ? "/" . $subdomain : "");
        }

        return $this->getProtocol() . "://" . $this->getParameterBag('base.assets') . $subdomain;
    }

    public function assets($subdomain = true)
    {
        return $this->getAssets($subdomain);
    }

    public function getVendor()
    {
        return $this->getAssets(false) . "/vendor";
    }
    public function vendor()
    {
        return $this->getVendor();
    }
}