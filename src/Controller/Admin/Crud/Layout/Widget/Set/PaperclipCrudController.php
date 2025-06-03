<?php

namespace Base\Controller\Admin\Crud\Layout\Widget\Set;

use Base\Controller\Admin\Crud\Layout\WidgetCrudController;

/**
 *
 */
class PaperclipCrudController extends WidgetCrudController
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
