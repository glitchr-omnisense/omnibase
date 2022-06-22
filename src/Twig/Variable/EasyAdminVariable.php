<?php

namespace Base\Twig\Variable;

use Base\Controller\Backend\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class EasyAdminVariable
{
    public function __construct(AdminUrlGenerator $adminUrlGenerator) { $this->adminUrlGenerator = $adminUrlGenerator; }
    public function crudify(mixed $entity)
    {
        return $this->adminUrlGenerator->unsetAll()
                    ->setController(AbstractCrudController::getCrudControllerFqcn($entity))
                    ->setEntityId($entity->getId())
                    ->setAction(Crud::PAGE_EDIT)
                    ->includeReferrer()
                    ->generateUrl();
    }
}
