<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Attribute;

use Base\Controller\Dashboard\Crud\Sitemap\AttributeCrudController;

class ArrayAttributeCrudController extends AttributeCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };
        return parent::configureFields($pageName, $callbacks);
    }
}
