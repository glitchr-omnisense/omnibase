<?php

namespace Base\Controller\Admin\Crud\Extension;

use Base\Controller\Admin\AbstractCrudController;

class RevisionCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }
}
