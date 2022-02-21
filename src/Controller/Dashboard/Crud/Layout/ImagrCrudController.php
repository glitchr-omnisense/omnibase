<?php

namespace Base\Controller\Dashboard\Crud\Layout;

use Base\Field\TranslationField;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\CropperField;
use Base\Field\ImageField;

class SettingCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield ImageField::new('source');
            yield CropperField::new('crops');

        }, $args);
    }
}