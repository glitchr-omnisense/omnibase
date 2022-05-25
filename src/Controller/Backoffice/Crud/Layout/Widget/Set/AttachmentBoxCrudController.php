<?php

namespace Base\Controller\Backoffice\Crud\Layout\Widget\Set;

use Base\Controller\Backoffice\Crud\Layout\WidgetCrudController;

class AttachmentBoxCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, [], $args);
    }
}
