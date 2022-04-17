<?php

namespace Base\Controller\Backoffice\Crud\Layout;

use Base\Field\TranslationField;

use Base\Controller\Backoffice\AbstractCrudController;
use Base\Entity\Layout\ImageCrop;
use Base\Field\AssociationField;
use Base\Field\CollectionField;
use Base\Field\CropperField;
use Base\Field\ImageField;
use Base\Field\Type\CropperType;

class ImageCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield ImageField::new('source')->setCropper([]);
            // yield CropperField::new('crops');
        }, $args);
    }
}