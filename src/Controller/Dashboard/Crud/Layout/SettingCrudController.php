<?php

namespace Base\Controller\Dashboard\Crud\Layout;

use Base\Field\TranslationField;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\BooleanField;
use Base\Field\SlugField;
use Base\Field\Type\QuillType;

class SettingCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield SlugField::new('path');
            yield BooleanField::new('secure');

            yield TranslationField::new("label")->renderAsHtml();
            yield TranslationField::new("help" )->renderAsHtml()->setRequired(false)
                    ->setFields(["help" => ["form_type" => QuillType::class]])
                    ->setExcludedFields("value");

        }, $args);
    }
}