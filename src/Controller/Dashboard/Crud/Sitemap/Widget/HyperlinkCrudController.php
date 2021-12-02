<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Widget;

use Base\Field\TranslatableField;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\EntityField;
use Base\Field\PatternField;
use Base\Field\RelationField;
use Base\Field\SelectField;

class HyperlinkCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() { return "fas fa-link"; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield EntityField::new('hyperpattern');
        foreach ( ($callbacks["hyperpattern"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield PatternField::new('variables')->setPatternFieldName("hyperpattern");
        foreach ( ($callbacks["variables"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslatableField::new()->showOnIndex('title')->setRequired(false);
        foreach ( ($callbacks["title"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}