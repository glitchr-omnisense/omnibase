<?php

namespace Base\Controller\Dashboard\Crud\Layout\Widget;

use Base\Field\TranslationField;

use Base\Controller\Dashboard\Crud\Layout\WidgetCrudController;
use Base\Field\SelectField;
use Base\Field\SlugField;
use Base\Field\Type\QuillType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class MenuCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield SelectField::new('widgets')->turnVertical();
            yield SlugField::new('path')->setSeparator("-")->setTargetFieldName("translations.title");
            yield TranslationField::new()->showOnIndex('title')->setFields([
                "title"   => TextType::class,
                "excerpt" => TextareaType::class,
                "content" => QuillType::class,
            ]);
        }, $args);
    }
}