<?php

namespace Base\Controller\Backoffice\Crud\User;

use Base\Admin\Controller\AbstractCrudController;
use Base\Field\ArrayField;
use Base\Field\AssociationField;
use Base\Field\ColorField;
use Base\Field\DateTimeField;
use Base\Field\IconField;
use Base\Field\IdField;
use Base\Field\TextField;
use Base\Entity\User\Group;
use DateTime;

class GroupCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Group::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-users';
    }

    public function createEntity(string $entityFqcn): object
    {
        $group = new $entityFqcn();
        $group->setCreatedAt(new DateTime());
        $group->setIcon('fa-solid fa-users');

        return $group;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name')->setColumns(5);
        yield IconField::new('icon')->setColumns(3);
        yield ColorField::new('color')->setColumns(4)->hideOnIndex();
        yield ArrayField::new('roles')->hideOnIndex();
        yield AssociationField::new('permissions')->hideOnIndex();
        // hideOnForm(), not hideOnIndex(): rendering this as a User-entity
        // picker on the new/edit form crashes with the same "No choices...
        // could be guessed" SelectType error as Group::$roles - building
        // the picker apparently pulls in User's own admin field list
        // (including its RoleField) outside a valid form-data context.
        // Membership is still visible/manageable via the index and detail
        // views; only the new/edit form widget is affected.
        yield AssociationField::new('members')->hideOnForm();
        yield DateTimeField::new('createdAt')->onlyOnDetail();
    }
}
