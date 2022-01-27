<?php

namespace Base\Controller\Dashboard\Crud\Layout\Widget;

use Base\Field\ImageField;
use Base\Field\TranslationField;
use Base\Field\SlugField;

use Base\Controller\Dashboard\Crud\Layout\WidgetCrudController;
use Base\Field\Type\QuillType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class PageCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield ImageField::new('thumbnail');
            yield SlugField::new('slug')->setTargetFieldName("translations.title");

        }, $args);
    }
}