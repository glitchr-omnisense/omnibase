<?php

namespace Base\Controller\Dashboard\Crud\Thread;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\DiscriminatorField;
use Base\Field\IdField;
use Base\Field\TranslationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;

class TagCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() : ?string { return null; } 
    
    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function() {
            
            yield IdField::new('id')->hideOnForm();
            yield DiscriminatorField::new('id')->hideOnForm()->showColumnLabel();
            yield TranslationField::new()->setTextAlign(TextAlign::RIGHT)->hideOnDetail();
        });
    }
}