<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Widget;

use Base\Field\TranslatableField;

use Base\Controller\Dashboard\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class HyperlinkCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() { return "fas fa-link"; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield UrlField::new('url');

        yield TranslatableField::new()->showOnIndex('title')->setRequired(false);
        foreach ( ($callbacks["title"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}