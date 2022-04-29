<?php

namespace Base\Controller\Backoffice\Crud\Thread;

use Base\Controller\Backoffice\AbstractCrudController;
use Base\Field\DiscriminatorField;
use Base\Field\IconField;
use Base\Field\SelectField;
use Base\Field\TranslationField;

class TaxonCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() : ?string { return null; } 
    
    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function() {
            
            yield DiscriminatorField::new('class')->hideOnForm()->showLastEntry();

            yield IconField::new('icon');
            yield SelectField::new('parent');
            yield SelectField::new('children');

            yield TranslationField::new();
        });
    }
}