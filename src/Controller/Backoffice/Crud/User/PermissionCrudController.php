<?php

namespace Base\Controller\Backoffice\Crud\User;

use Base\Admin\Controller\AbstractCrudController;
use Base\Field\ArrayField;
use Base\Field\AssociationField;
use Base\Field\IconField;
use Base\Field\IdField;
use Base\Field\TextareaField;
use Base\Field\TextField;
use Base\Entity\User\Permission;

class PermissionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Permission::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-lock';
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('tag')->setColumns(4);
        yield IconField::new('icon')->setColumns(2);
        yield TextareaField::new('description')->setColumns(6);
        // Roles this permission empowers when granted, independent of the
        // group hierarchy - see Permission::getEmpoweredRoles()/setEmpoweredRoles().
        // ArrayField, not RoleField: same SelectType choice-guessing issue
        // as Group::$roles (see GroupCrudController) since empower is a
        // plain json column too.
        yield ArrayField::new('empoweredRoles')->hideOnIndex();
        yield AssociationField::new('gid')->hideOnIndex();
    }
}
