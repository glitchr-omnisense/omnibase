<?php

namespace Base\Twig\Variable;

use Base\Controller\Backend\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class EasyAdminVariable
{
    /**
     * @var AdminUrlGenerator
     */
    protected $adminUrlGenerator;

    public function __construct(AdminUrlGenerator $adminUrlGenerator)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
    }
    public function crudify(mixed $entity)
    {
        if ($entity == null) {
            return null;
        }

        $entityCrudController = AbstractCrudController::getCrudControllerFqcn($entity);
        if ($entityCrudController == null) {
            return null;
        }

        return $this->adminUrlGenerator->unsetAll()
                    ->setController($entityCrudController)
                    ->setEntityId($entity->getId())
                    ->setAction(Crud::PAGE_EDIT)
                    ->includeReferrer()
                    ->generateUrl();
    }
}
