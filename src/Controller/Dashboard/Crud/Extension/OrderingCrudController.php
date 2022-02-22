<?php

namespace Base\Controller\Dashboard\Crud\Extension;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\ArrayField;
use Base\Field\CollectionField;
use Base\Field\DiscriminatorField;
use Base\Field\NumberField;
use Base\Field\SelectField;
use Base\Field\Type\ArrayType;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class OrderingCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function() {

            yield SelectField::new('action')->hideOnForm();
            yield TextField::new('entityClass')->setDisabled(true);
            yield NumberField::new('entityId')->setDisabled(true);
            yield CollectionField::new('entityData')->setEntryType(ArrayType::class);
        });
    }
}
