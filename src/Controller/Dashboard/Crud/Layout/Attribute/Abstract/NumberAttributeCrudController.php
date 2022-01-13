<?php

namespace Base\Controller\Dashboard\Crud\Layout\Attribute\Abstract;

use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class NumberAttributeCrudController extends AbstractAttributeCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        return parent::configureFields($pageName, array_merge($callbacks, [
            "id" => function () {
                yield NumberField::new('minimum')->setColumns(6);
                yield NumberField::new('maximum')->setColumns(6);
            },
        ]));
    }
}
