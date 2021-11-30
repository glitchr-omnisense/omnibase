<?php

namespace Base\Controller\Dashboard\Crud\Sitemap;

use Base\Field\LinkIdField;
use Base\Field\SlugField;
use Base\Field\TranslatableField;

use Base\Controller\Dashboard\AbstractCrudController;

class WidgetCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() { return "fas fa-square"; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield SlugField::new('slug')->setTargetFieldName("translations.title");
        foreach ( ($callbacks["slug"] ?? $defaultCallback)() as $yield)
            yield $yield;
    
        yield TranslatableField::new()->showOnIndex("title");
        foreach ( ($callbacks["title"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}