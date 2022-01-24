<?php

namespace Base\Controller\Dashboard\Crud\Layout\Widget;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use Base\Controller\Dashboard\Crud\Layout\WidgetCrudController;
use Base\Entity\Layout\Widget\Slot;
use Base\Field\SelectField;
use Base\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class SlotCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function createEntity(string $entityFqcn) { return new Slot(""); }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield SlugField::new('path')->setColumns(6)->setTargetFieldName("label");
            yield TextField::new("label")->setColumns(6);
            yield SelectField::new("widgets")->setColumns(12);
            yield TextareaField::new("help")->setColumns(12);
        }, $args);
    }
}