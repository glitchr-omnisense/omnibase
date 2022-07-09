<?php

namespace Base\Subscriber;

use InvalidArgumentException;

use EasyCorp\Bundle\EasyAdminBundle\Exception\EntityNotFoundException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

use Base\Controller\Backend\AbstractCrudController;
use Base\Entity\User\Notification;
use Base\Routing\RouterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;

class EasyAdminSubscriber implements EventSubscriberInterface
{
    public function __construct(RouterInterface $router, AdminContextProvider $adminContextProvider, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->router = $router;

        $this->adminContextProvider = $adminContextProvider;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => [['onKernelRequest']],
            KernelEvents::EXCEPTION => ['onKernelException'],
            AfterEntityUpdatedEvent::class => ['postEntityUpdate']
        ];
    }

    public function postEntityUpdate($entity)
    {
        $notification = new Notification("backoffice.update");
        $notification->send("success");
    }

    protected function getUrl(Request $request)
    {
        $request->overrideGlobals();

        $queryString = $request->getQueryString() ? "?".$request->getQueryString() : "";
        return explode("?", $request->getRequestUri())[0] . $queryString;
    }

    public function onKernelRequest(RequestEvent $e)
    {
        if($this->router->getMainRequest() != $e->getRequest()) return;
        if($this->router->isProfiler()) return;
        if(!$this->router->isEasyAdmin()) return;

        $crud = $this->adminContextProvider->getContext()->getCrud();
        if($crud == null) return;
        try {

            $entity = $this->adminContextProvider->getContext()->getEntity();
            $entityCrudController = AbstractCrudController::getCrudControllerFqcn($entity->getInstance());

        } catch (\TypeError $e) { return; }

        // Redirect to proper CRUD controller
        if($entityCrudController == null) return;

        // Calling child CRUD controller
        if($entityCrudController != $crud->getControllerFqcn() && !empty($crud->getCurrentPage())) {

            $instance = $entity->getInstance();
            $url = $this->adminUrlGenerator->unsetAll()
                ->setController($entityCrudController)
                ->setEntityId($instance ? $instance->getId() : null)
                ->setAction($crud->getCurrentAction())
                ->generateUrl();

            $e->setResponse($this->router->redirect($url));
        }
    }

    public function onKernelException(ExceptionEvent $e)
    {
        if(!$this->router->isDebug()) {

            $request   = $e->getRequest();
            $exception = $e->getThrowable();

            $eaCanonicException = true;
            switch(get_class($exception)) {

                case InvalidArgumentException::class :
                    $request->query->remove("crudControllerFqcn");
                case EntityNotFoundException::class :
                    $request->query->remove("entityId");
                case ForbiddenActionException::class :
                    if($request->query->get("crudAction") !== null) $request->query->set("crudAction", "index");
                    else $request->query->remove("crudAction");

                $eaCanonicException = false;
            }

            if(!$eaCanonicException)
                $e->setResponse($this->router->redirect($request));

            $e->stopPropagation();
        }
    }

}
