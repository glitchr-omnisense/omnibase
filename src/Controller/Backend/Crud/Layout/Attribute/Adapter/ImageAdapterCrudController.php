<?php

namespace Base\Controller\Backend\Crud\Layout\Attribute\Adapter;

use Base\Controller\Backend\Crud\Layout\Attribute\Adapter\Common\AbstractAdapterCrudController;

/**
 *
 */
class ImageAdapterCrudController extends AbstractAdapterCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }
}
