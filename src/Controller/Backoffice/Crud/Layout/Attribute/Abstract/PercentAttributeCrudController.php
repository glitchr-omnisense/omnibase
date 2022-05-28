<?php

namespace Base\Controller\Backoffice\Crud\Layout\Attribute\Abstract;

use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class PercentAttributeCrudController extends AbstractAttributeCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            //     yield NumberField::new('epsilon')->setColumns(6);
            //     yield NumberField::new('scale')->setColumns(6);
        }, $args);
    }
}
