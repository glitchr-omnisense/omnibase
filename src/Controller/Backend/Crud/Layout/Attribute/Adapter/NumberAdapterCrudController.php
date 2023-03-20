<?php

namespace Base\Controller\Backend\Crud\Layout\Attribute\Adapter;

use Base\Controller\Backend\Crud\Layout\Attribute\Adapter\Common\AbstractAdapterCrudController;
use Base\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class NumberAdapterCrudController extends AbstractAdapterCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {
            yield NumberField::new('minimum')->setColumns(6);
            yield NumberField::new('maximum')->setColumns(6);
        }, $args);
    }
}
