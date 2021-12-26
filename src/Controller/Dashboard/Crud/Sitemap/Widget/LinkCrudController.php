<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Widget;

use Base\Field\TranslationField;

use Base\Controller\Dashboard\Crud\Sitemap\WidgetCrudController;
use Base\Field\AttributeField;
use Base\Field\Type\QuillType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class LinkCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield AttributeField::new('hyperlink');
        foreach ( ($callbacks["hyperlink"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslationField::new()->showOnIndex('title')->setFields([
            "title" => [],
            "excerpt" => ["form_type" => TextareaType::class],
            "content" => ["form_type" => QuillType::class],
        ]);
        foreach ( ($callbacks["title"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}
