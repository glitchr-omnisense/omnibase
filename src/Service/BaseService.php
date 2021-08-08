<?php

namespace Base\Service;

use Symfony\Component\HttpFoundation\Response;

use Base\Controller\BaseController;
use Base\Entity\User;
use Base\Service\Traits\BaseNotificationTrait;
use Base\Service\Traits\BaseSymfonyTrait;
use Base\Service\Traits\BaseUtilsTrait;
use Base\Service\Traits\BaseTwigTrait;

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
use Base\Twig\Loader\FilesystemLoader;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Symfony\Component\Notifier\Channel\ChannelPolicy;
use Symfony\Component\Notifier\Channel\ChannelPolicyInterface;

use Twig\Environment; //https://symfony.com/doc/current/templating/twig_extension.html
use Twig\Extension\RuntimeExtensionInterface;

use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\String\Slugger\SluggerInterface;

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
    private $rstack;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var AdminContextProvider
     */
    private $adminContextProvider;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var Security
     */
    private $security;

    public function __construct(
        KernelInterface $kernel,
        Environment $twig,
        EntityManagerInterface $entityManager,
        FormFactoryInterface $formFactory,
        CsrfTokenManagerInterface $csrfTokenManager,
        NotifierInterface $notifier, ChannelPolicyInterface $notifierPolicy,
        SluggerInterface $slugger)
    {
        BaseController::$foundBaseService = true;

        $this->kernel  = $kernel;
        $this->container = $kernel->getContainer();
        $this->environment = $kernel->getEnvironment(); // "dev", "prod", etc..

        $this->setStartTime();

        // Security service is subdivided into authorization_checker, token, ..
        // Therefore the "Security" class is just a helper here
        $this->security = new Security($this->container);

        // Symfony basic services
        $this->twig             = $twig;

        $this->csrfTokenManager = $csrfTokenManager;
        $this->formFactory      = $formFactory;
        $this->entityManager    = $entityManager;

        $this->rstack     = $this->container->get("request_stack");
        $this->router     = $this->container->get("router");

        // Additional services related to user class
        $this->setTranslator($this->container->get("translator"));
        $this->setNotifier($notifier, $notifierPolicy);
        $this->setSlugger($slugger);
        $this->setProjectDir($this->kernel->getProjectDir());

        $this->setUserProperty($this->getParameterBag("base.user.property"));

        // Specific EA provider
        $this->adminContextProvider = new AdminContextProvider($this->rstack);

        $this->addJavascriptFile("/bundles/base/app.js");

        $this->initSymfonyTrait();
    }


    /*
     * Stylesheet and javascripts blocks
     */
    use BaseTwigTrait;

    /**
     * Symfony kernel container related methods
     */
    use BaseSymfonyTrait;

    /*
     * Util methods
     */
    use BaseUtilsTrait;

    /*
     * Notifications & flash messages
     */
    use BaseNotificationTrait;
}