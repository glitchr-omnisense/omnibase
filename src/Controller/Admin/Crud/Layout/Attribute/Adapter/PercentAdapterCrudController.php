<?php

namespace Base\Controller\Admin\Crud\Layout\Attribute\Adapter;

use Base\Controller\Admin\Crud\Layout\Attribute\Adapter\Common\AbstractAdapterCrudController;

/**
 *
 */
class PercentAdapterCrudController extends AbstractAdapterCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {
            //     yield NumberField::new('epsilon')->setColumns(6);
            //     yield NumberField::new('scale')->setColumns(6);
        }, $args);
    }
}
