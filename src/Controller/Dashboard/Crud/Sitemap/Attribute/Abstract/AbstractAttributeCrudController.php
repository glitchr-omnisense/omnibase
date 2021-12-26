<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Attribute\Abstract;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\DiscriminatorField;
use Base\Field\FontAwesomeField;
use Base\Field\SlugField;
use Base\Field\TranslationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;

class AbstractAttributeCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };
        foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield DiscriminatorField::new("type")->setTextAlign(TextAlign::RIGHT);
        yield FontAwesomeField::new('icon')->setTextAlign(TextAlign::LEFT)->setColumns(6);
        foreach ( ($callbacks["icon"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield SlugField::new('code')->setColumns(6)->setTargetFieldName("translations.label");
        foreach ( ($callbacks["code"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslationField::new("label");
        yield TranslationField::new()->showOnIndex("help");
        foreach ( ($callbacks["translations"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}
