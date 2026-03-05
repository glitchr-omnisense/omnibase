<?php

namespace Base\Controller\Admin\Crud\Layout\Attribute\Adapter;

use Base\Controller\Admin\Crud\Layout\Attribute\Adapter\Common\AbstractAdapterCrudController;

class TextareaAdapterCrudController extends AbstractAdapterCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }
}
