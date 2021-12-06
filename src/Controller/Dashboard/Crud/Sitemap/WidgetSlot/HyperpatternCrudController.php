<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\WidgetSlot;

use Base\Field\FontAwesomeField;
use Base\Field\SlugField;
use Base\Field\TranslationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Entity\Sitemap\Widget\Hyperlink;
use Base\Field\EntityField;
use Base\Field\RelationField;

class HyperpatternCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield FontAwesomeField::new('icon');
        foreach ( ($callbacks["icon"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TextField::new("pattern")->hideOnIndex();

        yield SlugField::new('path')->setTargetFieldName("translations.label");
        foreach ( ($callbacks["path"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslationField::new("label");

        yield EntityField::new("hyperlinks")->renderAsCount()->hideOnForm();

        yield TextField::new('pattern');
        foreach ( ($callbacks["pattern"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}
