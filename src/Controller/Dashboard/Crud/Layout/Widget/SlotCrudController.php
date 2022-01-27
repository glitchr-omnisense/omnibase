<?php

namespace Base\Controller\Dashboard\Crud\Layout\Widget;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use Base\Controller\Dashboard\Crud\Layout\WidgetCrudController;
use Base\Entity\Layout\Widget\Slot;
use Base\Field\QuillField;
use Base\Field\SelectField;
use Base\Field\SlugField;
use Base\Field\TranslationField;
use Base\Field\Type\QuillType;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class SlotCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function createEntity(string $entityFqcn) { return new Slot(""); }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, ["id" => function () {

            yield SelectField::new("widgets")->setColumns(6)->setFilter("^".Slot::class);
            yield SlugField::new('path')->setColumns(6)->setTargetFieldName("translations.title");

        }], $args);
    }
}