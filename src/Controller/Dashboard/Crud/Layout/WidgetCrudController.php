<?php

namespace Base\Controller\Dashboard\Crud\Layout;

use Base\Field\TranslationField;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\DiscriminatorField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;

class WidgetCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield DiscriminatorField::new()->setTextAlign(TextAlign::RIGHT);
            yield TranslationField::new()->showOnIndex("title");
        
        }, $args);
    }
}