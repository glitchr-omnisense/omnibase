<?php

namespace Base\Controller\Dashboard\Crud\Extension;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\ArrayField;
use Base\Field\CollectionField;
use Base\Field\DiscriminatorField;
use Base\Field\NumberField;
use Base\Field\SelectField;
use Base\Field\Type\ArrayType;

class OrderingCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function() {

            yield SelectField::new('action');
            yield NumberField::new('entityId')->hideOnForm();
            yield DiscriminatorField::new('entityClass');
            yield CollectionField::new('entityData', [
                "entry_type" => ArrayType::class
            ]);
        });
    }
}
