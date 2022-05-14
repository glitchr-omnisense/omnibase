<?php

namespace Base\Controller\Backoffice\Crud\Layout;

use Base\Field\TranslationField;

use Base\Controller\Backoffice\AbstractCrudController;
use Base\Entity\Layout\Setting;
use Base\Field\BooleanField;
use Base\Field\SlugField;
use Base\Field\Type\QuillType;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SettingCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function createEntity(string $entityFqcn) { return new Setting(""); }
    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield SlugField::new('path')->setColumns(6)->setTargetFieldName("translations.label");
            yield TextField::new('bag')->setColumns(6);
            yield BooleanField::new('locked')->setColumns(6)->withConfirmation();

            yield BooleanField::new('secure')->setColumns(6)->renderAsSwitch(false)->onlyOnIndex();
            yield BooleanField::new('secure')->setColumns(6)->hideOnIndex()->withConfirmation();

            yield TranslationField::new("label")->renderAsHtml();
            yield TranslationField::new("help" )->renderAsHtml()->setRequired(false)
                    ->setFields(["help" => ["form_type" => QuillType::class]])
                    ->setExcludedFields("value");

        }, $args);
    }
}