<?php

namespace Base\Controller\Backoffice\Crud;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Field\AvatarField;
use Base\Admin\Field\BooleanField;
use Base\Admin\Field\DateField;
use Base\Admin\Field\DateTimeField;
use Base\Admin\Field\EmailField;
use Base\Admin\Field\IdField;
use Base\Admin\Field\PasswordField;
use Base\Admin\Field\RoleField;
use Base\Admin\Filter\Filters;
use Base\Entity\User;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-user';
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('email')->add('createdAt');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield BooleanField::new('isApproved')->setColumns(2);
        yield AvatarField::new('avatar')->setColumns(2)->hideOnDetail();
        yield RoleField::new('roles')->setColumns(5);
        yield EmailField::new('email')->setColumns(5);
        yield DateField::new('birthdate')->hideOnIndex()->setColumns(2);
        yield PasswordField::new('plainPassword')->onlyOnForms()->setRequired(false)->setColumns(10);
        yield DateTimeField::new('activeAt')->hideOnForm();
        yield DateTimeField::new('updatedAt')->onlyOnDetail();
        yield DateTimeField::new('createdAt')->onlyOnDetail();
    }
}
