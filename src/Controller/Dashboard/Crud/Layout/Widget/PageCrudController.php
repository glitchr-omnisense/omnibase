<?php

namespace Base\Controller\Dashboard\Crud\Layout\Widget;

use Base\Field\ImageField;
use Base\Field\SlugField;

use Base\Controller\Dashboard\Crud\Layout\WidgetCrudController;

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