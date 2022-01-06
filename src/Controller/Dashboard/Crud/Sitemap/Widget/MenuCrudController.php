<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Widget;

use Base\Field\TranslationField;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Base\Controller\Dashboard\AbstractCrudController;
use Base\Controller\Dashboard\Crud\Sitemap\WidgetCrudController;
use Base\Field\SelectField;

class MenuCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };
        return parent::configureFields($pageName, [
            "id" => function () use ($defaultCallback, $callbacks, $pageName) {

                yield SelectField::new('widgets')->turnVertical();
                foreach ( ($callbacks["widgets"] ?? $defaultCallback)() as $yield)
                    yield $yield;

                yield TranslationField::new()->showOnIndex('title');
                foreach ( ($callbacks["title"] ?? $defaultCallback)() as $yield)
                    yield $yield;
        }]);
    }
}