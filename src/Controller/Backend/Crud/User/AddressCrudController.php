<?php

namespace Base\Controller\Backend\Crud\User;

use Base\Controller\Backend\AbstractCrudController;
use Base\Field\CountryField;
use Base\Field\DateTimePickerField;
use Base\Field\DiscriminatorField;
use Base\Field\SelectField;
use Base\Field\SlugField;
use Base\Field\StateField;
use Base\Field\TranslationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class AddressCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {
            yield CountryField::new('country')->showFirst()->setTextAlign(TextAlign::LEFT);
            yield TextField::new('state')->setColumns(6);

            yield TextField::new('zipCode')->setColumns(6);
            yield TextField::new('city')->setColumns(6);
            yield TextField::new('streetAddress')->setColumns(6);
        }, $args);
    }
}
