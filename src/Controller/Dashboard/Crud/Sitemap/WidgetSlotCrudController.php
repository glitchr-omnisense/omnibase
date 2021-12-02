<?php

namespace Base\Controller\Dashboard\Crud\Sitemap;

use Base\Field\TranslatableField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use Base\Controller\Dashboard\AbstractCrudController;

class WidgetSlotCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() { return "fas fa-th-large"; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield TextField::new('name');
        foreach ( ($callbacks["name"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslatableField::new("label")->setRequired(false);
        yield TranslatableField::new()->showOnIndex("help")->setRequired(false);
        foreach ( ($callbacks["help"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}