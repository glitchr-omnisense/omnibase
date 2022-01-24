<?php

namespace Base\Controller\Dashboard\Crud\Layout;

use Base\Field\TranslationField;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Base\Controller\Dashboard\AbstractCrudController;

class SettingCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield TextField::new('name');

            yield TranslationField::new("label");
            yield TranslationField::new()->showOnIndex("help")->setRequired(false)->setExcludedFields("value");

        }, $args);
    }
}