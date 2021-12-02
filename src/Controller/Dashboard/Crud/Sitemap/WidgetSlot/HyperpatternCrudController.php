<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\WidgetSlot;

use Base\Field\FontAwesomeField;
use Base\Field\SlugField;
use Base\Field\TranslatableField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use Base\Controller\Dashboard\AbstractCrudController;

class HyperpatternCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() { return "fas fa-share-alt"; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield TextField::new("urlPattern")->hideOnIndex();
        foreach ( ($callbacks["urlPattern"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield FontAwesomeField::new('icon');
        foreach ( ($callbacks["icon"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslatableField::new("label");

        yield TextField::new('name')->hideOnForm();
        foreach ( ($callbacks["name"] ?? $defaultCallback)() as $yield)
            yield $yield;
    
        yield SlugField::new('patternName')->hideOnIndex()->setTargetFieldName("translations.label");
        foreach ( ($callbacks["patternName"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TextField::new('urlPattern');
        foreach ( ($callbacks["urlPattern"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}