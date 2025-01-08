<?php

namespace Base\Admin\Router;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Registry\DashboardControllerRegistryInterface;

class AdminUrlGenerator extends \EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator
{
    /**
     * @EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;
        
    public function __construct(
        AdminContextProviderInterface $adminContextProvider, 
        UrlGeneratorInterface $urlGenerator, 
        DashboardControllerRegistryInterface $dashboardControllerRegistry, 
        AdminRouteGeneratorInterface $adminRouteGenerator, 
        EntityManagerInterface $entityManager)
    {
        parent::__construct($adminContextProvider, $urlGenerator, $dashboardControllerRegistry, $adminRouteGenerator);
        $this->entityManager = $entityManager;
    }

    protected function setRouteParameter(string $paramName, $paramValue): void
    {
        if(is_instanceof($paramValue, AbstractCrudController::class)) {

            $entityFqcn = $paramValue::getEntityFqcn();
            $tableName  = $this->entityManager->getConfiguration()->getNamingStrategy()->classToTableName($entityFqcn);
            $paramValue = camel2snake(snake2camel($tableName, "_"), '-');
        }

        parent::setRouteParameter($paramName, $paramValue);
    }


    public function generateUrl(): string
    {
        return parent::generateUrl();
    }
}
