<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Widget;

use Base\Field\ImageField;
use Base\Field\TranslationField;
use Base\Field\SlugField;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\Type\QuillType;

class PageCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield ImageField::new('thumbnail');
        yield SlugField::new('slug')->setTargetFieldName("translations.title");

        yield TranslationField::new()->showOnIndex('title')->setFields([
            "content" => ["form_type" => QuillType::class],
        ]);
        foreach ( ($callbacks["title"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}