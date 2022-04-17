<?php

namespace Base\Controller\Backoffice\Crud\Layout\Widget;

use Base\Controller\Backoffice\Crud\Layout\WidgetCrudController;
use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Slot;
use Base\Field\DiscriminatorField;
use Base\Field\SelectField;
use Base\Field\SlugField;
use Base\Field\TranslationField;
use Base\Field\Type\QuillType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class SlotCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function createEntity(string $entityFqcn) { return new $entityFqcn(""); }
    public function configureFields(string $pageName, ...$args): iterable
    {
        yield DiscriminatorField::new()->setTextAlign(TextAlign::RIGHT);
        yield SlugField::new('path')->setColumns(6)->setTargetFieldName("translations.label");
        yield SelectField::new("widget")->setColumns(6)->setClass(Widget::class)->setFilter("^".Slot::class);
        
        yield TranslationField::new('label')->autoload(false)->setFields([
            "label" => TextType::class,
            "help"  => QuillType::class,
        ]);
    }
}