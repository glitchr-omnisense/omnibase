<?php

namespace Base\Service;

use App\Entity\User;
use Base\Controller\Backend\AbstractCrudController;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Entity\EntityHydratorInterface;
use Base\Routing\RouterInterface;
use Base\Traits\BaseTrait;

use Base\Service\ParameterBagInterface;
use Symfony\Component\Form\FormInterface;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use Base\Traits\BaseCommonTrait;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Event\PreUpdateEventArgs;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
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

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\Stopwatch\Stopwatch;

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
     * @var CsrfTokenManagerInterface
     */
    protected $csrfTokenManager;

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

    public function __construct(
        KernelInterface $kernel,
        RequestStack $requestStack,
        FirewallMapInterface $firewallMap,

        Environment $twig,

        SluggerInterface $slugger,
        ManagerRegistry $doctrine,

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

        TranslatorInterface $translator,
        RouterInterface $router,

        EntityHydratorInterface $entityHydrator,
        ClassMetadataManipulator $classMetadataManipulator,
        AdminUrlGenerator $adminUrlGenerator)
    {
        $this->setInstance($this);
        $this->startTime($kernel->getStartTime());

        // Kernel and additional stopwatch
        $this->kernel       = $kernel;
        $this->container    = $kernel->getContainer();
        $this->setProjectDir ($kernel->getProjectDir());
        $this->setEnvironment($kernel->getEnvironment());

        $this->authorizationChecker = $authorizationChecker;
        $this->csrfTokenManager     = $csrfTokenManager;
        $this->formFactory          = $formFactory;

        // Additional common containers
        $this->setClassMetadataManipulator($classMetadataManipulator);
        $this->setImageService($imageService);
        $this->setIconProvider($iconProvider);
        $this->setSettingBag($settingBag);
        $this->setLocaleProvider($localeProvider);
        $this->setTwig($twig);
        $this->setRouter($router);
        $this->setFirewallMap($firewallMap);
        $this->setParameterBag($parameterBag);
        $this->setTranslator($translator);
        $this->setSlugger($slugger);
        $this->setDoctrine($doctrine);
        $this->setEntityHydrator($entityHydrator);
        $this->setRequestStack($requestStack);
        $this->setUserIdentifier($this->getParameterBag()->get("base.user.identifier"));
        $this->setTokenStorage($tokenStorage);
        $this->setNotifier($notifier);

        // EA provider
        $this->adminContextProvider = new AdminContextProvider($requestStack);
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public function getHomepage()  { return $this->getParameterBag()->get("base.site.homepage") ?? $this->getRouter()->getRoute("/"); }
    public function getSite()
    {
        return [
            "title"  => $this->getSettingBag()->getScalar("base.settings.title"),
            "slogan" => $this->getSettingBag()->getScalar("base.settings.slogan"),
            "logo"   => $this->getSettingBag()->getScalar("base.settings.logo")
        ];
    }
    public function getBackoffice()
    {
        return [
            "title"  => $this->getSettingBag()->getScalar("base.settings.title.backoffice"),
            "slogan" => $this->getSettingBag()->getScalar("base.settings.slogan.backoffice"),
            "logo"   => $this->getSettingBag()->getScalar("base.settings.logo.backoffice")
        ];
    }
    public function getEmail()
    {
        return [
            "title"  => $this->getSettingBag()->getScalar("base.settings.title.email"),
            "slogan" => $this->getSettingBag()->getScalar("base.settings.slogan.email"),
            "logo"   => $this->getSettingBag()->getScalar("base.settings.logo.email")
        ];
    }

    public function getMeta(?string $locale = null): array
    {
        return $this->getSettingBag()->get("base.settings.meta", $locale) ?? [];
    }


    public function exec(string $command, array $arguments = [])
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput(array_merge($arguments, ['command' => $command]));
        $output = new BufferedOutput();
        $application->run($input, $output);

        $content = $output->fetch();
        return new Response($content);
    }

    /*
     * Stylesheet and javascripts blocks
     */
    public function settings() { return $this->getSettingBag(); } // Used in twig environment
    public function crudify($entity): string {

        return $this->adminUrlGenerator->unsetAll()
            ->setController(AbstractCrudController::getCrudControllerFqcn($entity))
            ->setEntityId($entity->getId())
            ->setAction(Crud::PAGE_EDIT)
            ->includeReferrer()
            ->generateUrl();
    }

    public function getParameterTwig(string $name = "") { return $this->getTwig()->getParameterTwig($name); }
    public function addParameterTwig(string $name, $newValue) { return $this->getTwig()->addParameterTwig($name, $newValue); }
    public function hasParameterTwig(string $name) { return $this->getTwig()->hasParameter($name); }
    public function setParameterTwig(string $name, mixed $value) { return $this->getTwig()->setParameter($name, $value); }
    public function appendParameterTwig($name, mixed $value) { return $this->getTwig()->appendParameter($name, $value); }
    public function renderHtmlContent(string $location) { return $this->getTwig()->renderHtmlContent($location); }
    public function getHtmlContent(string $location) { return $this->getTwig()->getHtmlContent($location); }
    public function removeHtmlContent(string $location) { return $this->getTwig()->removeHtmlContent($location); }
    public function addHtmlContent(string $location, $contentOrArrayOrFile, array $options = []) { return $this->getTwig()->addHtmlContent($location,$contentOrArrayOrFile,$options); }

    /**
     *
     * Symfony kernel container related methods
     *
     */
    protected $startTime = 0;
    public function getExecutionTime(): float { return round(microtime(true) - $this->startTime, 2); }
    public function startTime($startTime = null)
    {
        $this->$startTime = $startTime;
        if(!$this->startTime || is_infinite($this->startTime))
            $this->startTime = microtime(true);
    }

    public function hasGet()     { return isset($_GET); }
    public function hasPost()    { return isset($_POST); }
    public function hasSession() { return isset($_SESSION); }
    public function addSession($name, $value) { $this->getSession()->set($name, $value); }
    public function removeSession($name) { return ($this->getRequestStack() && $this->getRequestStack()->getSession()->has($name)) ? $this->getRequestStack()->getSession()->remove($name) : null; }
    public function getSession($name = null)
    {
        if(!$name) return $this->getRequestStack()->getSession();
        return ($this->getRequestStack() && $this->getRequestStack()->getSession()->has($name)) ? $this->getRequestStack()->getSession()->get($name) : null;
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

    public function getAsset(string $url): string { return $this->getTwig()->getAsset($url); }
        // $url = trim($url);
        // $parse = parse_url($url);
        // if($parse["scheme"] ?? false)
        //     return $url;

        // $request = $this->getRequestStack()->getCurrentRequest();
        // $baseDir = $request ? $request->getBasePath() : $_SERVER["CONTEXT_PREFIX"] ?? "";
        // $baseDir = $baseDir ."/";
        // $path = trim($parse["path"]);
        // if($path == "/") return $baseDir ? $baseDir : "/";
        // else if(!str_starts_with($path, "/"))
        //     $path = $baseDir.$path;

        // return $path ? $path : null;

    public function        getRequest(): ?Request { return $this->getRouter()->getRequest(); }
    public function getCurrentRequest(): ?Request { return $this->getRequest(); }

    public function        getRoute(?string $url): ?string { return $this->getRouter()->getRoute($url); }
    public function getCurrentRoute(): ?string { return $this->getRouter()->getRoute(); }
    public function         getRouteName(?string $url): ?string { return $this->getRouter()->getRouteName($url); }
    public function getCurrentRouteName(): ?string { return $this->getRouter()->getRouteName(); }

    public function generateUrl(string $routeName, array $routeParameters = []): string { return $this->getRouter()->generate($routeName, $routeParameters); }

    public function redirect(string $urlOrRoute, array $routeParameters = [], int $state = 302, array $headers = []): RedirectResponse
    {
        if(filter_var($urlOrRoute, FILTER_VALIDATE_URL) || str_contains($urlOrRoute, "/")) return new RedirectResponse($urlOrRoute);
        return new RedirectResponse($this->getRouter()->generate($urlOrRoute, $routeParameters), $state, $headers);
    }

    public function redirectToRoute(string $routeName, array $routeParameters = [], int $state = 302, array $headers = []): ?RedirectResponse
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

        $url   = $this->getRouter()->generate($routeName, $routeParameters) ?? $routeName;
        $routeName = $this->getRouteName($url);
        if (!$routeName)
            throw new RouteNotFoundException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeNameBak));

        foreach($exceptions as $pattern)
            if (preg_match($pattern, $this->getCurrentRouteName())) return null;

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

        return $this->authorizationChecker->isGranted($attribute, $subject);
    }













    /**
     *
     * Doctrine related methods
     *
     */

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

        } else if($inDoctrineStack === true && $this->inDoctrineStack()) {

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
