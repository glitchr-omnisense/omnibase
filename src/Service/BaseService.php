<?php

namespace Base\Service;

use Base\Controller\BaseController;
use Base\Service\Traits\BaseNotificationTrait;
use Base\Service\Traits\BaseSecurityTrait;
use Base\Service\Traits\BaseSymfonyTrait;
use Base\Service\Traits\BaseTwigTrait;
use Base\Service\Traits\BaseDoctrineTrait;
use Base\Traits\BaseTrait;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Security;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use Base\Service\Traits\BaseCommonTrait;
use Base\Twig\Extension\BaseTwigExtension;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;

use Twig\Environment; //https://symfony.com/doc/current/templating/twig_extension.html
use Twig\Extension\RuntimeExtensionInterface;

use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

use Symfony\Component\HttpFoundation\RequestStack;

class BaseService implements RuntimeExtensionInterface
{
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
     * @var Container
     */
    protected $container;
    public function getContainer($name) { return ($name ? $this->container->get($name) : $this->container); }
    public function getAvailableServices(): array
    {
        if (!isset($this->container))
            throw new \Exception("Symfony container not found in BaseService. Did you overloaded BaseService::__construct ?");

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
        BaseSettings $settings)
    {
        BaseController::$foundBaseService = true;

        // Kernel and additional stopwatch
        $this->kernel      = $kernel;
        $this->container   = $kernel->getContainer();
        $this->setProjectDir($kernel->getProjectDir());
        $this->setStartTime();
        
        self::$twigExtension       = $baseTwigExtension->setBase($this);

        // Symfony basics
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage         = $tokenStorage;
        $this->csrfTokenManager     = $csrfTokenManager;
        $this->formFactory          = $formFactory;
        $this->requestStack         = $requestStack;

        // Additional containers
        $this->setSettings($settings);
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
     * Common variables between traits
     */
    use BaseTrait;
    use BaseCommonTrait;

    /*
     * Stylesheet and javascripts blocks
     */
    use BaseTwigTrait;

    /**
     * Symfony kernel container related methods
     */
    use BaseSymfonyTrait;

    /**
     * Security container related methods
     */
    use BaseSecurityTrait;

    /**
     * Doctrine related methods
     */
    use BaseDoctrineTrait;
}
