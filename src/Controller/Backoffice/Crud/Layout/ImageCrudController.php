<?php

namespace Base\Controller\Backoffice\Crud\Layout;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Field\IdField;
use Base\Admin\Field\ImageField;
use Base\Admin\Field\TextField;
use Base\Entity\Layout\Image;

class ImageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Image::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-image';
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield ImageField::new('source');
        yield TextField::new('quality')->onlyOnIndex();
    }
}
