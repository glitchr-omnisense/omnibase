<?php

namespace Base\Controller\Backend\Crud\Layout\Attribute\Adapter;

use Base\Controller\Backend\Crud\Layout\Attribute\Adapter\Common\AbstractAdapterCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class TextAdapterCrudController extends AbstractAdapterCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, fn () => yield NumberField::new('length')->setColumns(12), $args);
    }
}
