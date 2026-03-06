<?php

namespace Base\Admin\EventListener;

use Base\Controller\Admin\AbstractCrudController;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Registry\DashboardControllerRegistryInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class AdminRouterSubscriber extends \EasyCorp\Bundle\EasyAdminBundle\EventListener\AdminRouterSubscriber
{
    protected EntityManager $entityManager;
    protected DashboardControllerRegistryInterface $dashboardRegistry;

    public function __construct(DashboardControllerRegistryInterface $dashboardRegistry, EntityManager $entityManager, ...$args)
    {
        $this->dashboardRegistry = $dashboardRegistry;
        $this->entityManager = $entityManager;
        parent::__construct(...$args);
    }

    /**
     * Resolve abstract dashboard controllers to their concrete subclass.
     * Base bundle routes serve as fallbacks; app routes take priority.
     */
    private function resolveAbstractDashboard(string $fqcn): string
    {
        if ((new ReflectionClass($fqcn))->isAbstract()) {
            foreach ($this->dashboardRegistry->getAll() as $item) {
                $registeredFqcn = $item['controller'];
                if (is_subclass_of($registeredFqcn, $fqcn)) {
                    return $registeredFqcn;
                }
            }
        }

        return $fqcn;
    }

    /**
     * Resolves the dashboard FQCN and updates the _controller request attribute
     * so Symfony can instantiate the concrete class (not the abstract one).
     */
    protected function getDashboardControllerFqcn(Request $request): ?string
    {
        $fqcn = parent::getDashboardControllerFqcn($request);
        if ($fqcn === null) {
            return null;
        }

        $resolved = $this->resolveAbstractDashboard($fqcn);
        if ($resolved !== $fqcn) {
            $controller = $request->attributes->get('_controller');
            if (is_string($controller)) {
                $request->attributes->set('_controller', str_replace($fqcn, $resolved, $controller));
            }
        }

        return $resolved;
    }

    /**
     * Covers the pretty-URL path (onKernelRequestPrettyUrls) which reads the FQCN
     * directly from the dashboard routes cache and skips getDashboardControllerFqcn().
     */
    protected function getDashboardControllerInstance(string $dashboardControllerFqcn, Request $request): ?DashboardControllerInterface
    {
        $resolvedControllerFqcn = $this->resolveAbstractDashboard($dashboardControllerFqcn);
        if ($resolvedControllerFqcn !== $dashboardControllerFqcn) {
            $controllerFqcn = $request->attributes->get('_controller');
            $request->attributes->set('_controller', str_replace($dashboardControllerFqcn, $resolvedControllerFqcn, $controllerFqcn));
            if (is_string($controllerFqcn)) {
                // must be resolved as an array for EA to instantiate the concrete class instead of fallinback to ::index
                $request->attributes->set('_controller', explode("::", $request->attributes->get('_controller')));
            }
        }

        return parent::getDashboardControllerInstance($resolvedControllerFqcn, $request);
    }

    protected function getCrudControllerInstance(Request $request): ?CrudControllerInterface
    {
        $crudControllerFqcn = $request->attributes->get(EA::CRUD_CONTROLLER_FQCN);
        if($crudControllerFqcn) {
            $request->query->set(EA::CRUD_CONTROLLER_FQCN, $crudControllerFqcn);
        }

        $entityFqcn = $request->query->get(EA::CRUD_CONTROLLER_FQCN);
        if($entityFqcn && class_exists($entityFqcn)) $request->query->set(EA::CRUD_CONTROLLER_FQCN, $entityFqcn);
        else {

            $namingStrategy = $this->entityManager->getConfiguration()->getNamingStrategy();
            $entityFqcnTable = camel2snake(snake2camel($namingStrategy->classToTableName($entityFqcn),"_"),"-");
            
            $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
            foreach ($allMetadata as $metadata) {
                $tableName = camel2snake(snake2camel($namingStrategy->classToTableName($metadata->getName()),"_"),"-");
                
                if($tableName == $entityFqcnTable) {
                    $request->query->set(EA::CRUD_CONTROLLER_FQCN, AbstractCrudController::getCrudControllerFqcn($metadata->getName()));
                    break;
                }
            }
        }
        
        $crudAction = $request->attributes->get(EA::CRUD_ACTION);
        if($crudAction) {
            $request->query->set(EA::CRUD_ACTION, $crudAction);
        }
        
        $entityId = $request->attributes->get(EA::ENTITY_ID);
        if($entityId) {
            $request->query->set(EA::ENTITY_ID, $entityId);
        }

        return parent::getCrudControllerInstance($request);
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        
        $crudControllerFqcn = $request->query->get(EA::CRUD_CONTROLLER_FQCN);
        if($crudControllerFqcn) {
            $request->attributes->set(EA::CRUD_CONTROLLER_FQCN, $crudControllerFqcn);
            $request->query->remove(EA::CRUD_CONTROLLER_FQCN);

        }

        $crudAction = $request->query->get(EA::CRUD_ACTION);
        if($crudAction) {
            $request->attributes->set(EA::CRUD_ACTION, $crudAction);
            $request->query->remove(EA::CRUD_ACTION);
        }
        
        $entityId = $request->attributes->get(EA::ENTITY_ID);
        if($entityId) {
            $request->attributes->set(EA::ENTITY_ID, $entityId);
            $request->query->remove(EA::ENTITY_ID);
        }

        parent::onKernelController($event);
    }
}
