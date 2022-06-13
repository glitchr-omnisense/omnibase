<?php

namespace Base\Subscriber;

use Base\Controller\Backoffice\AbstractCrudController;
use Base\Service\BaseService;
use EasyCorp\Bundle\EasyAdminBundle\Exception\EntityNotFoundException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class EasyAdminSubscriber implements EventSubscriberInterface
{
    public function __construct(BaseService $baseService, AdminContextProvider $adminContextProvider, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->baseService = $baseService;

        $this->adminContextProvider = $adminContextProvider;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => [['onKernelRequest']],
            KernelEvents::EXCEPTION => ['onKernelException']
        ];
    }

    protected function getUrl(Request $request)
    {
        $request->overrideGlobals();

        $queryString = $request->getQueryString() ? "?".$request->getQueryString() : "";
        return explode("?", $request->getRequestUri())[0] . $queryString;
    }

    public function onKernelRequest(RequestEvent $e)
    {
        if($this->baseService->getRequestStack()->getMainRequest() != $e->getRequest()) return;
        if($this->baseService->isProfiler()) return;
        if(!$this->baseService->isEasyAdmin()) return;

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

            $e->setResponse($this->baseService->redirect($url));
        }
    }

    public function onKernelException(ExceptionEvent $e)
    {
        if(!$this->baseService->isDebug()) {

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
                $e->setResponse($this->baseService->redirect($request));

            $e->stopPropagation();
        }
    }

}
