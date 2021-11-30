<?php

namespace Base\Controller\Dashboard\Crud\Sitemap;

use Base\Field\TranslatableField;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Base\Controller\Dashboard\AbstractCrudController;

class SettingCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() { return "fas fa-tools"; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield TextField::new('name');
        foreach ( ($callbacks["name"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslatableField::new("label");
        yield TranslatableField::new()->showOnIndex("help")->setRequired(false)->setExcludedFields("value");
        foreach ( ($callbacks["value"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}