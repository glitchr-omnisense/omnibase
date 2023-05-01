<?php

namespace Base\Controller\Backend\Crud\User;

use Base\Controller\Backend\AbstractCrudController;
use Base\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AddressCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {
            yield CountryField::new('country')->showFirst()->setTextAlign(TextAlign::LEFT);
            yield TextField::new('state')->setColumns(6);

            yield TextField::new('zipCode')->setColumns(6);
            yield TextField::new('city')->setColumns(6);
            yield TextField::new('streetAddress')->setColumns(6);
        }, $args);
    }
}
