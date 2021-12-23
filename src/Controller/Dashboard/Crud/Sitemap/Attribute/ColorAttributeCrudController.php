<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Attribute;

use Base\Controller\Dashboard\Crud\Sitemap\AttributeCrudController;
use Base\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ColorAttributeCrudController extends AttributeCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        return parent::configureFields($pageName, array_merge($callbacks, [
            "id" => function () {
                yield ColorField::new('value')->setColumns(6);
            },
        ]));
    }
}
