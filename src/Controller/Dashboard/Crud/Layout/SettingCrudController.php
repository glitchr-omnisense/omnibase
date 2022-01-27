<?php

namespace Base\Controller\Dashboard\Crud\Layout;

use Base\Field\TranslationField;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\Type\QuillType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\TextEditorType;

class SettingCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield TextField::new('name');

            yield TranslationField::new("label")->renderAsHtml();
            yield TranslationField::new("help" )->renderAsHtml()->setRequired(false)
                    ->setFields(["help" => ["form_type" => QuillType::class]])
                    ->setExcludedFields("value");

        }, $args);
    }
}