<?php

namespace Base\Service;

use App\Entity\User;
use Base\Database\Factory\ClassMetadataManipulator;
use Base\Database\Factory\EntityHydrator;
use Base\Traits\BaseTrait;

use Base\Service\ParameterBagInterface;
use Symfony\Component\Form\FormInterface;

use Symfony\Component\HttpKernel\KernelInterface;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use Base\Traits\BaseCommonTrait;
use Base\Twig\Extension\BaseTwigExtension;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Event\PreUpdateEventArgs;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

use Twig\Environment; //https://symfony.com/doc/current/templating/twig_extension.html
use Twig\Extension\RuntimeExtensionInterface;

use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\EventDispatcher\Event;

// TODO: Clean up advanced router proxy methods;
class BaseService implements RuntimeExtensionInterface
{
    use BaseTrait;
    use BaseCommonTrait;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var AdminContextProvider
     */
    protected $adminContextProvider;

    /**
     * @var AuthorizationCheckerInterface
     */
    protected $authorizationChecker;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var CsrfTokenManagerInterface
     */
    protected $csrfTokenManager;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var Container
     */
    protected $container;
    public function getContainer($name) { return ($name ? $this->container->get($name) : $this->container); }
    public function getAvailableServices(): array
    {
        if (!isset($this->container))
            throw new \Exception("Symfony container not found in BaseService. Did you overloaded self::__construct ?");

        return $this->container->getServiceIds();
    }

    /**
     * @var RequestStack
     */
    protected $requestStack;
    public function getRequestStack(): RequestStack { return $this->requestStack; }

    /**
     * @var BaseTwigExtension
     */
    protected static $twigExtension = null;
    public static function getTwigExtension(): BaseTwigExtension { return self::$twigExtension; }

    public function __construct(
        KernelInterface $kernel,
        RequestStack $requestStack,
        Environment $twig,
        BaseTwigExtension $baseTwigExtension,

        SluggerInterface $slugger,
        EntityManagerInterface $entityManager,

        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        CsrfTokenManagerInterface $csrfTokenManager,

        ParameterBagInterface $parameterBag,
        NotifierInterface $notifier,
        FormFactoryInterface $formFactory,
        LocaleProviderInterface $localeProvider,

        SettingBag $settingBag,
        ImageService $imageService,
        IconProvider $iconProvider,

        EntityHydrator $entityHydrator,
        ClassMetadataManipulator $classMetadataManipulator)
    {
        $this->setInstance($this);

        // Kernel and additional stopwatch
        $this->kernel      = $kernel;
        $this->container   = $kernel->getContainer();
        $this->setProjectDir($kernel->getProjectDir());
        $this->setStartTime();

        self::$twigExtension       = $baseTwigExtension->setBase($this);

        // Symfony basics
        $this->authorizationChecker     = $authorizationChecker;
        $this->tokenStorage             = $tokenStorage;
        $this->csrfTokenManager         = $csrfTokenManager;
        $this->formFactory              = $formFactory;
        $this->requestStack             = $requestStack;
        $this->entityHydrator           = $entityHydrator;

        // Additional containers
        $this->setClassMetadataManipulator($classMetadataManipulator);
        $this->setImageService($imageService);
        $this->setIconProvider($iconProvider);
        $this->setSettingBag($settingBag);
        $this->setLocaleProvider($localeProvider);
        $this->setTwig($twig);
        $this->setRouter($this->container->get("router"));
        $this->setParameterBag($parameterBag);
        $this->setTranslator($this->container->get("translator"));
        $this->setSlugger($slugger);
        $this->setEntityManager($entityManager);
        $this->setEnvironment($this->kernel->getEnvironment());
        $this->setUserIdentifier($this->getParameterBag("base.user.identifier"));
        $this->setNotifier($notifier);

        // EA provider
        $this->adminContextProvider = new AdminContextProvider($this->requestStack);
    }






    /*
     * Stylesheet and javascripts blocks
     */
    public function settings() { return $this->getSettingBag(); }

    public function getParameterTwig(string $name = "")
    {
        if (!isset(self::$twig))
            throw new Exception("No twig found in BaseService. Did you overloaded self::__construct ?");

        $globals = self::$twig->getGlobals();
        if(!$name) return $globals;

        return (array_key_exists($name, $globals)) ? $globals[$name] : null;
    }

    public function addParameterTwig(string $name, $newValue)
    {
        if (!isset(self::$twig))
            throw new Exception("No twig found in BaseService. Did you overloaded self::__construct ?");

        $value = $this->getParameterTwig($name);
        if ($value == null) $value = $newValue;
        else {

            if (is_string($value)) $value .= "\n" . $newValue;
            else if (is_array($value)) $value += array_merge($value, $newValue);
            else if (is_numeric($value)) $value += $newValue;
            else if (is_object($value) && is_object($newValue) && method_exists($value, '__add')) $value += $newValue;
            else throw new Exception("Ambiguity for merging the two \"$name\" entities..");
        }

        return self::$twig->addGlobal($name, $value);
    }

    public function hasParameterTwig(string $name)
    {
        if (!isset(self::$twig))
            throw new Exception("No twig found in BaseService. Did you overloaded self::__construct ?");

        return self::$twig->getGlobals()[$name] ?? null;
    }

    public function setParameterTwig(string $name, $value)
    {
        if (!isset(self::$twig))
            throw new Exception("No twig found in BaseService. Did you overloaded self::__construct ?");

        return self::$twig->addGlobal($name, $value);
    }

    public function appendParameterTwig($name, $value)
    {
        if (!isset(self::$twig))
            throw new Exception("No twig found in BaseService. Did you overloaded self::__construct ?");

        $parameter = self::$twig->getGlobals()[$name] ?? null;
        if(is_string($parameter)) self::$twig->addGlobal($name, $parameter.$value);
        if( is_array($parameter)) self::$twig->addGlobal($name, array_merge($parameter,$value));
        throw new Exception("Unknown merging method for \"$name\"");
    }

    private $htmlContent = [];

    public function renderHtmlContent(string $location)
    {
        $htmlContent = $this->getHtmlContent($location);
        if(!empty($htmlContent))
            $this->removeHtmlContent($location);

        return $htmlContent;
    }

    public function getHtmlContent(string $location)
    {
        return trim(implode(PHP_EOL,array_unique($this->htmlContent[$location] ?? [])));
    }

    public function removeHtmlContent(string $location)
    {
        if(array_key_exists($location, $this->htmlContent))
            unset($this->htmlContent[$location]);

        return $this;
    }

    public function addHtmlContent(string $location, $contentOrArrayOrFile, array $options = [])
    {
        if(empty($contentOrArrayOrFile)) return $this;

        if(is_array($contentOrArrayOrFile)) {

            foreach($contentOrArrayOrFile as $content)
                $this->addHtmlContent($location, $content, $options);

            return $this;
        }

        $relationship = pathinfo_relationship($contentOrArrayOrFile);
        if(!$relationship) {

            $content = $contentOrArrayOrFile;

        } else {

            // Compute options
            $relationship = $options["rel"] ?? $relationship;
            array_values_remove($options, "rel");

            $attributes = html_attributes($options);

            // Convert into html tag
            switch($relationship) {

                case "javascript":
                    $content = "<script src='".$this->getAsset($contentOrArrayOrFile)."' ".$attributes."></script>";
                    break;

                case "icon":
                case "preload":
                case "stylesheet":
                default:
                    $content = "<link rel='".$relationship."' href='".$this->getAsset($contentOrArrayOrFile)."' ".$attributes.">";
                    break;
            }
        }

        if(!array_key_exists($location, $this->htmlContent))
            $this->htmlContent[$location] = [];

        $this->htmlContent[$location][] = $content;

        return $this;
    }











    /**
     *
     * Symfony kernel container related methods
     *
     */

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

    public function getLocale(?string $locale = null) { return self::getLocaleProvider()->getLocale($locale); }

    public function getSalt()   { return $this->getSecret(); }
    public function getSecret() { return $this->getParameterBag("kernel.secret"); }
    public function getProfiler() { return $this->kernel->getContainer()->get('profiler'); }
    public function getProfile($response = null)
    {
        if (!$response) return null;
        return $this->getProfiler()->loadProfileFromResponse($response);
    }

    public function getParameter(string $name): array|bool|string|int|float|null { return $this->kernel->getContainer()->getParameter($name); }
    public function hasParameter(string $name): bool { return $this->kernel->getContainer()->hasParameter($name); }
    public function setParameter(string $name, array|bool|string|int|float|null $value) { return $this->kernel->getContainer()->setParameter($name, $value); }

    public function getAsset(string $url): string
    {
        $url = trim($url);
        $parse = parse_url($url);
        if($parse["scheme"] ?? false)
            return $url;

        $request = $this->requestStack->getCurrentRequest();
        $baseDir = $request ? $request->getBasePath() : $_SERVER["CONTEXT_PREFIX"] ?? "";
        $baseDir = $baseDir ."/";
        $path = trim($parse["path"]);
        if($path == "/") return $baseDir ? $baseDir : "/";
        else if(!str_starts_with($path, "/"))
            $path = $baseDir.$path;

        return $path ? $path : null;
    }


    public function getRequest(): ?Request { return $this->getRouter()->getRequest(); }
    public function getCurrentRequest(): ?Request { return $this->getRequest(); }

    public function hasRoute(string $routeName): bool { return $this->getRouter()->hasRoute($routeName); }
    public function getRoute(?string $url): ?string { return $this->getRouter()->getRoute($url); }
    public function getCurrentRoute(): ?string { return $this->getRouter()->getRoute(); }
    public function getRouteName(?string $url): ?string { return $this->getRouter()->getRouteName($url); }
    public function getCurrentRouteName(): ?string { return $this->getRouter()->getRouteName(); }

    public function redirect(string $urlOrRoute, array $routeParameters = [], int $state = 302, array $headers = []): RedirectResponse
    {
        if(filter_var($urlOrRoute, FILTER_VALIDATE_URL)) return new RedirectResponse($urlOrRoute);
        return new RedirectResponse($this->getRouter()->generate($urlOrRoute, $routeParameters), $state, $headers);
    }

    public function redirectToRoute(string $routeName, array $routeParameters = [], int $state = 302, array $headers = []): RedirectResponse
    {
        $routeNameBak = $routeName;

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

        $url   = $this->generateUrl($routeName, $routeParameters) ?? $routeName;
        $routeName = $this->getRouteName($url);
        if (!$routeName) throw new RouteNotFoundException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeNameBak));

        $currentRoute = $this->getCurrentRouteName();
        if ($routeName == $currentRoute) return null;

        $exceptions = is_string($exceptions) ? [$exceptions] : $exceptions;
        foreach($exceptions as $pattern)
            if (preg_match($pattern, $currentRoute)) throw new RouteNotFoundException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeNameBak));

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

    public function isMaintenance() { return $this->getSettingBag()->maintenance() || file_exists($this->getParameterBag("base.maintenance.lockpath")); }
    public function isDevelopment() { return $this->isDebug() || $this->kernel->getEnvironment() == "dev" || str_starts_with($this->kernel->getEnvironment(), "dev_"); }
    public function isProduction()  { return !$this->isDevelopment(); }

    public function isCli() { return is_cli(); }
    public function isDebug() { return $this->kernel->isDebug(); }
    public function isEasyAdmin() { return $this->getRouter()->isEasyAdmin(); }
    public function isProfiler() { return $this->getRouter()->isProfiler(); }
    public function isEntity($entityOrClassOrMetadata) : bool { return $this->getClassMetadataManipulator()->isEntity($entityOrClassOrMetadata); }


    /**
     *
     * Security container related methods
     *
     */

    public function setUserIdentifier(string $userIdentifier)
    {
        User::$identifier = $userIdentifier;
        return $this;
    }

    public function Logout()
    {
        if (!isset($this->tokenStorage))
            throw new Exception("No token storage found in BaseService. Did you overloaded self::__construct ?");

        $this->tokenStorage->setToken(null);
        if(array_key_exists("REMEMBERME", $_COOKIE))
            setcookie("REMEMBERME", '', time()-1);
    }

    public function isCsrfTokenValid(string $id, $tokenOrForm, ?Request $request = null, string $csrfFieldId = "_csrf_token"): bool
    {
        if (!isset($this->csrfTokenManager))
            throw new Exception("No CSRF token manager found in BaseService. Did you overloaded self::__construct ?");

        // Prepare token parameter

        $token = null;
        if (!$tokenOrForm instanceof FormInterface) $token = $tokenOrForm;
        else {

            $form = $tokenOrForm;
            if($request == null)
                throw new Exception("Request required as FormInterface provided");

            //$form->handleRequest($request); // TBC
            if($request->request->has($form->getName()))
                $token = $request->request->get($form->getName())[$csrfFieldId] ?? null;
        }

        // Handling CSRF token exception
        if($token && !is_string($token))
            throw new Exception("Unexpected token value provided: string expected");

        // Checking validity
        return $this->csrfTokenManager->isTokenValid(new CsrfToken($id, $token));
    }

    public function getToken()
    {
        if (!isset($this->tokenStorage))
            throw new Exception("No token storage found in BaseService. Did you overloaded self::__construct ?");

        return $this->tokenStorage->getToken();
    }

    public function getUser()
    {
        if (!$token = $this->getToken())
            return null;

        $user = $token->getUser();
        if (!\is_object($user))
            return null;

        if (!$user instanceof UserInterface)
            return null;

        return $user;
    }

    public function isGranted($attribute, $subject = null): bool
    {
        if (!isset($this->authorizationChecker))
            throw new Exception("No authorization checker found in BaseService. Did you overloaded self::__construct ?");

        if ($this->getToken() === null) return false;
        return $this->authorizationChecker->isGranted($attribute, $subject);
    }













    /**
     *
     * Doctrine related methods
     *
     */
    public function setEntityManager(EntityManagerInterface $entityManager) { $this->entityManager = $entityManager; }
    public function getEntityManager(bool $reopen = false): ?EntityManagerInterface
    {
        if (!$this->entityManager) return null;
        if (!$this->entityManager->isOpen()) {

            if(!$reopen) return null;
            $this->entityManager = $this->entityManager->create(
                $this->entityManager->getConnection(),
                $this->entityManager->getConfiguration()
            );
        }

        return $this->entityManager;
    }

    public function inDoctrine($entity): bool
    {
        if(!is_object($entity)) return false;
        return $this->entityManager->contains($entity);
    }

    public function inDoctrineStack()
    {
        $debug_backtrace = debug_backtrace();
        foreach($debug_backtrace as $trace)
            if(str_starts_with($trace["class"], "Doctrine")) return true;

        return false;
    }

    public function getOriginalEntityData($eventOrEntity, bool $inDoctrineStack = false, bool $reopen = false)
    {
        $entity = $eventOrEntity->getObject();
        $originalEntityData = $this->getEntityManager($reopen)->getUnitOfWork()->getOriginalEntityData($entity);

        if($eventOrEntity instanceof PreUpdateEventArgs) {

            $event = $eventOrEntity;
            foreach($event->getEntityChangeSet() as $field => $data)
                $originalEntityData[$field] = $data[0];

        } else if($inDoctrineStack && $this->inDoctrineStack()) {

            throw new \Exception("Achtung ! You are trying to access data object within a Doctrine method..".
                                "Original entity might have already been updated.");
        }

        return $originalEntityData;
    }

    protected static $entitySerializer = null;
    public function getOriginalEntity($eventOrEntity, bool $inDoctrineStack = false, bool $reopen = false)
    {
        if(!self::$entitySerializer)
            self::$entitySerializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);

        $data = $this->getOriginalEntityData($eventOrEntity, $inDoctrineStack, $reopen);

        if(!$eventOrEntity instanceof LifecycleEventArgs) $entity = $eventOrEntity;
        else $entity = $eventOrEntity->getObject();

        $oldEntity = $this->entityHydrator->hydrate($entity, $data);

        return $oldEntity;
    }
}
