<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Attribute;

use Base\Controller\Dashboard\Crud\Sitemap\AttributeCrudController;
use Base\Field\DiscriminatorField;
use Base\Field\FontAwesomeField;
use Base\Field\SlugField;
use Base\Field\TranslationField;

class AbstractAttributeCrudController extends AttributeCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };
        foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield FontAwesomeField::new('icon')->setColumns(6);
        foreach ( ($callbacks["icon"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield SlugField::new('code')->setColumns(6)->setTargetFieldName("translations.label");
        foreach ( ($callbacks["code"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield DiscriminatorField::new("type");

        yield TranslationField::new("label");
        yield TranslationField::new()->showOnIndex("help");
    }
}
