<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Attribute;

use Base\Controller\Dashboard\Crud\Sitemap\AttributeCrudController;
use Base\Field\DateTimePickerField;

class DateTimeAttributeCrudController extends AttributeCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        return parent::configureFields($pageName, array_merge($callbacks, [
            "id" => function () {
                yield DateTimePickerField::new('value');
            },
        ]));
    }
}
