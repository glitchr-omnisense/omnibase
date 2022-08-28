<?php

namespace Base\Controller\Backend\Crud\Layout\Attribute\Adapter;

use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class PercentAdapterCrudController extends AbstractAdapterCrudController
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
