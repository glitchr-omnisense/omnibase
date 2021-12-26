<?php

namespace Base\Controller\Dashboard\Crud\Sitemap;

use Base\Controller\Dashboard\AbstractCrudController;

class AttributeCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };
        return parent::configureFields($pageName, $callbacks);
    }
}