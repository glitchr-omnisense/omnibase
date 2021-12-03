<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\WidgetSlot;

use Base\Field\FontAwesomeField;
use Base\Field\SlugField;
use Base\Field\TranslationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use Base\Controller\Dashboard\AbstractCrudController;

class HyperpatternCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() { return "fas fa-share-alt"; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield FontAwesomeField::new('icon');
        foreach ( ($callbacks["icon"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TextField::new("pattern")->hideOnIndex();

        yield SlugField::new('name')->setTargetFieldName("translations.label");
        foreach ( ($callbacks["name"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslationField::new("label");

        yield TextField::new('pattern');
        foreach ( ($callbacks["pattern"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}
