<?php

namespace Base\Controller\Backend\Crud\Layout\Attribute\Abstract;

use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class NumberAttributeCrudController extends AbstractAttributeCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield NumberField::new('minimum')->setColumns(6);
            yield NumberField::new('maximum')->setColumns(6);

        }, $args);
    }
}
