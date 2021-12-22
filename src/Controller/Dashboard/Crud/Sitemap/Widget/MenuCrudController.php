<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Widget;

use Base\Field\TranslationField;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Base\Controller\Dashboard\AbstractCrudController;
use Base\Controller\Dashboard\Crud\Sitemap\WidgetCrudController;

class MenuCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield TextField::new('name');
        foreach ( ($callbacks["name"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslationField::new()->showOnIndex('title')->setRequired(false);
        foreach ( ($callbacks["title"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}