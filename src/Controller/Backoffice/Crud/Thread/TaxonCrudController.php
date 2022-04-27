<?php

namespace Base\Controller\Backoffice\Crud\Thread;

use Base\Controller\Backoffice\AbstractCrudController;
use Base\Field\DiscriminatorField;

class TaxonCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() : ?string { return null; } 
    
    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function() {
            
            yield DiscriminatorField::new('class')->hideOnForm()->showColumnLabel();
        });
    }
}