<?php

namespace Base\Controller\Dashboard\Crud\Thread;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\DiscriminatorField;
use Base\Field\IdField;
use Base\Field\TranslationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TagCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() : ?string { return null; } 
    
    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield IdField::new('id')->hideOnForm();
        foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield DiscriminatorField::new('id')->hideOnForm()->showColumnLabel();
        foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslationField::new()->setTextAlign(TextAlign::RIGHT)->hideOnDetail();
        foreach ( ($callbacks["translations"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }
}