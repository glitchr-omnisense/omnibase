<?php

namespace Base\Controller\Backend\Crud\User;

use Base\Controller\Backend\AbstractCrudController;
use Base\Field\CollectionField;
use Base\Field\DateTimePickerField;
use Base\Field\LocaleField;
use Base\Field\NumberField;
use Base\Field\SelectField;
use Base\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

/**
 *
 */
class ConnectionCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield SelectField::new('user')->setColumns(3);
            yield SelectField::new('impersonator')->setColumns(3)->setRequired(false);
            yield TextField::new('uniqid')->hideOnIndex()->setDisabled()->setColumns(3);
            yield SelectField::new('state')->setColumns(3);

            yield TextareaField::new('agent')->setColumns(6)->hideOnIndex();
            yield LocaleField::new('locale')->setColumns(3);
            yield NumberField::new('loginAttempts')->hideOnForm()->setColumns(3);
           
            yield DateTimePickerField::new('createdAt')->onlyOnDetail();
            yield DateTimePickerField::new('updatedAt')->setColumns(3);

            yield CollectionField::new('ipList');
            yield CollectionField::new('timezones');
            yield CollectionField::new('hostnames');

        }, $args);
    }
}
