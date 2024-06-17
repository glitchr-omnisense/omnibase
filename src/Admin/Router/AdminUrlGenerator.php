<?php

namespace Base\Admin\Router;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Registry\DashboardControllerRegistry;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Registry\CrudControllerRegistry;

class AdminUrlGenerator extends \EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator
{
    /**
     * @EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;

    public function __construct(AdminContextProvider $adminContextProvider, UrlGeneratorInterface $urlGenerator, DashboardControllerRegistry $dashboardControllerRegistry, CrudControllerRegistry $registry, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct($adminContextProvider, $urlGenerator, $dashboardControllerRegistry);
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
