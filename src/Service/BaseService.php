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
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;

use Twig\Environment; //https://symfony.com/doc/current/templating/twig_extension.html
use Twig\Extension\RuntimeExtensionInterface;

use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

use Symfony\Component\HttpFoundation\RequestStack;

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
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;
    
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;
    
    /**
     * @var CsrfTokenManagerInterface
     */
    private $csrfTokenManager;
    

    public function __construct(
        KernelInterface $kernel,
        RequestStack $requestStack,
        Environment $twig,

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
        $this->kernel  = $kernel;
        $this->setStartTime();

        // Symfony basics
        $this->container = $kernel->getContainer();
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->formFactory      = $formFactory;
        $this->requestStack     = $requestStack;

        // Additional containers
        $this->setSettings($settings);
        $this->setLocaleProvider($localeProvider);
        $this->setTwig($twig);
        $this->setRouter($this->container->get("router"));
        $this->setParameterBag($parameterBag);
        $this->setTranslator($this->container->get("translator"));
        $this->setSlugger($slugger);
        $this->setEntityManager($entityManager);
        $this->setProjectDir($this->kernel->getProjectDir());
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
