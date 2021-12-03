<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Widget;

use Base\Field\TranslationField;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Base\Controller\Dashboard\AbstractCrudController;

class MenuCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() { return "fas fa-compass"; } 

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