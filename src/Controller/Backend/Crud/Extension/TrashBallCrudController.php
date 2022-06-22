<?php

namespace Base\Controller\Backend\Crud\Extension;

use Base\Controller\Backend\AbstractCrudController;
use Base\Field\CollectionField;
use Base\Field\DateTimePickerField;
use Base\Field\NumberField;
use Base\Field\SelectField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TrashBallCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function() {

            yield SelectField::new('action')->hideOnForm();
            yield TextField::new('entityClass')->setDisabled(true);
            yield NumberField::new('entityId')->setDisabled(true);

            yield DateTimePickerField::new('permanentAfter');
        });
    }
}
