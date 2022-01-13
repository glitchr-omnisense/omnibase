<?php

namespace Base\Controller\Dashboard\Crud\Layout\Widget;

use Base\Field\ImageField;
use Base\Field\TranslationField;
use Base\Field\SlugField;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Controller\Dashboard\Crud\Layout\WidgetCrudController;
use Base\Field\Type\QuillType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class PageCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };
        return parent::configureFields($pageName, [
            "id" => function () use ($defaultCallback, $callbacks, $pageName) {

                yield ImageField::new('thumbnail');

                yield SlugField::new('slug')->setTargetFieldName("translations.title");
                yield TranslationField::new()->showOnIndex('title')->setFields([
                    "excerpt" => ["form_type" => TextareaType::class],
                    "content" => ["form_type" => QuillType::class],
                ]);
                foreach ( ($callbacks["title"] ?? $defaultCallback)() as $yield)
                    yield $yield;
        }]);
    }
}