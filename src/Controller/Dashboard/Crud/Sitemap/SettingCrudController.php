<?php

namespace Base\Controller\Dashboard\Crud\Sitemap;

use Base\Field\TranslationField;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Base\Controller\Dashboard\AbstractCrudController;

class SettingCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield TextField::new('name');
        foreach ( ($callbacks["name"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslationField::new("label");
        yield TranslationField::new()->showOnIndex("help")->setRequired(false)->setExcludedFields("value");
        foreach ( ($callbacks["value"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}