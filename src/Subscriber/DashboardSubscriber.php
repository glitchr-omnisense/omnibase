<?php

namespace Base\Subscriber;
use Base\Service\BaseService;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Exception\EntityRemoveException;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Base\Entity\User\Notification;

class DashboardSubscriber implements EventSubscriberInterface
{
    /**
     * @var AdminContextProvider
     */
    private $adminContextProvider;
    /**
     * @var AdminUrlGenerator
     */
    private $adminUrlGenerator;

    public function __construct(BaseService $baseService, AdminUrlGenerator$adminUrlGenerator, AdminContextProvider $adminContextProvider)
    {
        $this->baseService = $baseService;

        $this->adminContextProvider = $adminContextProvider;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getSubscribedEvents()
    {
        return [ KernelEvents::EXCEPTION => ['onKernelException'] ];
    }

    public function onKernelException(ExceptionEvent $exception)
    {
        // Check if exception happened in EasyAdmin (avoid warning outside EA)
        if(!$this->adminContextProvider) return;
        if(!$this->adminContextProvider->getContext()) return;

        // Get back exception & send flash message
        $notification = new Notification($exception);
        if (!empty($notification->getContent())) {

            if ($this->baseService->isDevelopment()) dump($exception);
            if ($this->baseService->isDevelopment()) $notification->send("danger");
        }

        // Get back crud information
        $crud       = $this->adminContextProvider->getContext()->getCrud();
        if(!$crud) return;

        $controller = $crud->getControllerFqcn();
        $action     = $crud->getCurrentPage();

        // Avoid infinite redirection
        // - If exception happened in "index", redirect to dashboard
        // - If exception happened in an other section, redirect to index page first
        // - If exception happened after submitting a form, just redirect to the initial page
        $url = $this->adminUrlGenerator->unsetAll();
        switch($action) {
            case "index": break;
            default:
                $url = $url->setController($controller);
        }

        $exception->setResponse(new RedirectResponse($url));
    }
}
