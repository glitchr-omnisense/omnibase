<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Widget;

use Base\Field\TranslationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use Base\Controller\Dashboard\Crud\Sitemap\WidgetCrudController;
use Base\Entity\Sitemap\Widget\Slot;

class SlotCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function createEntity(string $entityFqcn)
    {
        return new Slot("");
    }

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield TextField::new('path');
        foreach ( ($callbacks["path"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslationField::new("label")->setRequired(false);
        yield TranslationField::new()->showOnIndex("help")->setRequired(false);
        foreach ( ($callbacks["help"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}