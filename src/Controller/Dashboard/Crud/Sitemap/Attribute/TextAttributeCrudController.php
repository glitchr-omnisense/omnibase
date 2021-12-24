<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Attribute;

use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class TextAttributeCrudController extends AbstractAttributeCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        return parent::configureFields($pageName, array_merge($callbacks, [
            "id" => function () {
                yield NumberField::new('length')->setColumns(12);
            },
        ]));
    }
}
