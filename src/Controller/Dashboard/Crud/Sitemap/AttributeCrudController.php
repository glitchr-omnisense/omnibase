<?php

namespace Base\Controller\Dashboard\Crud\Sitemap;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\SelectField;
use Base\Field\TranslationField;
use EasyCorp\Bundle\EasyAdminBundle\Collection\ActionCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;

class AttributeCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };
        return parent::configureFields($pageName, [
            "id" => function () use ($defaultCallback, $callbacks, $pageName) {

                foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
                    yield $yield;

                yield SelectField::new("attributePattern")->withConfirmation();
                foreach ( ($callbacks["attributePattern"] ?? $defaultCallback)() as $yield)
                    yield $yield;

                yield TranslationField::new();
                foreach ( ($callbacks["translations"] ?? $defaultCallback)() as $yield)
                    yield $yield;
            }
        ]);
    }
}