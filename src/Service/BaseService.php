<?php

namespace Base\Service;

use Symfony\Component\HttpFoundation\Response;

use Base\Controller\BaseController;
use Base\Entity\User;
use Base\Service\Traits\BaseNotificationTrait;
use Base\Service\Traits\BaseSecurityTrait;
use Base\Service\Traits\BaseSymfonyTrait;
use Base\Service\Traits\BaseTwigTrait;
use Base\Service\Traits\BaseUtilsTrait;
use Base\Service\Traits\BaseDoctrineTrait;
use Base\Traits\BaseTrait;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use Symfony\Contracts\Translation\TranslatorInterface;
use Base\Repository\UserRepository;
use Base\Service\Traits\BaseCommonTrait;
use Base\Service\Traits\BaseUserTrait;
use Base\Twig\Loader\FilesystemLoader;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Symfony\Component\Notifier\Channel\ChannelPolicy;
use Symfony\Component\Notifier\Channel\ChannelPolicyInterface;

use Twig\Environment; //https://symfony.com/doc/current/templating/twig_extension.html
use Twig\Extension\RuntimeExtensionInterface;

use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;

/**
 * @class Base interface to be used with custom base
 */
final class BaseService implements RuntimeExtensionInterface
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var AdminContextProvider
     */
    private $adminContextProvider;

    /**
     * @var Security
     */
    private $security;

    public function __construct(
        KernelInterface $kernel,
        Environment $twig,
        ManagerRegistry $doctrine,
        FormFactoryInterface $formFactory, 
        LocaleProviderInterface $localeProvider,
        NotifierInterface $notifier, 
        ChannelPolicyInterface $notifierPolicy,
        SluggerInterface $slugger, 
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        CsrfTokenManagerInterface $csrfTokenManager,
        BaseSettings $settings)
    {
        BaseController::$foundBaseService = true;
        
        $this->kernel  = $kernel;
        $this->container = $kernel->getContainer();
        
        $this->setStartTime();
        $this->setSettings($settings);

        // Security service is subdivided into authorization_checker, token, ..
        // Therefore the "Security" class is just a helper here
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        
        // Symfony basic services
        $this->csrfTokenManager = $csrfTokenManager;
        $this->formFactory      = $formFactory;
        $this->requestStack           = $this->container->get("request_stack");
        $this->setLocaleProvider($localeProvider);

        $this->setTwig($twig);
        $this->setDoctrine($doctrine);
        $this->setEntityManager($doctrine->getManager());
        $this->setRouter($this->container->get("router"));
        
        // Additional services related to user class
        $this->setTranslator($this->container->get("translator"));
        $this->setSlugger($slugger);
        $this->setProjectDir($this->kernel->getProjectDir());
        $this->setEnvironment($this->kernel->getEnvironment());
        $this->setUserProperty($this->getParameterBag("base.user.property"));
        $this->setNotifier($notifier, $notifierPolicy, $this->getParameterBag("base.notifier.options"));
        
        // Specific EA provider
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

    /*
     * Util methods
     */
    use BaseUtilsTrait;

    /*
     * Notifications & flash messages
     */
    use BaseNotificationTrait;
}
