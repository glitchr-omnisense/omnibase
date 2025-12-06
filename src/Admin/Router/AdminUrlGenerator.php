<?php

namespace Base\Admin\Router;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class AdminUrlGenerator extends \EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator
{
    /**
     * @EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;
        
    public function __construct(...$args) {

        $this->entityManager = \array_pop_class(EntityManagerInterface::class, $args);
        parent::__construct(...$args);
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
        $url = parent::generateUrl();
        $url = compose_url(array_merge(\parse_url2(get_url()), \parse_url2($url)));

        return $url;
    }
}
