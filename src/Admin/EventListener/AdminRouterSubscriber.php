<?php

namespace Base\Admin\EventListener;

use App\Entity\Article\Article;
use App\Entity\Article\CommentReply;
use App\Entity\User;
use Base\Controller\Admin\AbstractCrudController;
use Base\Entity\User as EntityUser;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\CrudControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ControllerFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AdminRouterSubscriber extends \EasyCorp\Bundle\EasyAdminBundle\EventListener\AdminRouterSubscriber
{
    protected EntityManager $entityManager;
    public function __construct(AdminContextFactory $adminContextFactory, ControllerFactory $controllerFactory, ControllerResolverInterface $controllerResolver, UrlGeneratorInterface $urlGenerator, RequestMatcherInterface $requestMatcher, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct($adminContextFactory, $controllerFactory, $controllerResolver, $urlGenerator, $requestMatcher);
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