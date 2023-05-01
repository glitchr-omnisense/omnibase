<?php

namespace Base\Twig\Variable;

use Base\Controller\Backend\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

/**
 *
 */
class EasyAdminVariable
{
    protected AdminUrlGenerator $adminUrlGenerator;

    public function __construct(AdminUrlGenerator $adminUrlGenerator)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    /**
     * @param mixed $entity
     * @return string|null
     */
    public function crudify(mixed $entity)
    {
        if (null == $entity) {
            return null;
        }

        $entityCrudController = AbstractCrudController::getCrudControllerFqcn($entity);
        if (null == $entityCrudController) {
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
