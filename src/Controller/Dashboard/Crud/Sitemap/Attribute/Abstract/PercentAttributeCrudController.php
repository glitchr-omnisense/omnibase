<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Attribute\Abstract;

use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class PercentAttributeCrudController extends AbstractAttributeCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        return parent::configureFields($pageName, array_merge($callbacks, [
            // "id" => function () {
            //     yield NumberField::new('epsilon')->setColumns(6);
            //     yield NumberField::new('scale')->setColumns(6);
            // },
        ]));
    }
}
