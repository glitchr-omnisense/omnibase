<?php

namespace Base\Controller\Backoffice\Crud\Layout\Widget;

use Base\Field\ImageField;
use Base\Field\SlugField;

use Base\Controller\Backoffice\Crud\Layout\WidgetCrudController;
use Base\Entity\Layout\Widget\Page;
use Base\Field\SelectField;

class PageCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, ["id" => function () {

            yield ImageField::new('thumbnail')->setColumns(6);
            yield SlugField::new('slug')->setColumns(6)->setTargetFieldName("translations.title");

            yield SelectField::new('similars')->showFirst()->setColumns(6)->setFilter(Page::class);

        }], $args);
    }
}