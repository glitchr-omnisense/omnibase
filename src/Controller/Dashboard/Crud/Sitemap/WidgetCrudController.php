<?php

namespace Base\Controller\Dashboard\Crud\Sitemap;

use Base\Field\LinkIdField;
use Base\Field\SlugField;
use Base\Field\TranslationField;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\DiscriminatorField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;

class WidgetCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };
        return parent::configureFields($pageName, array_merge([

            "id" => function () use ($defaultCallback, $callbacks, $pageName) {

                yield DiscriminatorField::new()->setTextAlign(TextAlign::RIGHT);

                yield TranslationField::new()->showOnIndex("title");
                foreach ( ($callbacks["title"] ?? $defaultCallback)() as $yield)
                    yield $yield;

        }],$callbacks));
    }
}