<?php

namespace Base\Controller\Backend\Crud\Layout\Widget\Set;

use Base\Controller\Backend\Crud\Layout\WidgetCrudController;

class NetworkCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, [], $args);
    }
}
