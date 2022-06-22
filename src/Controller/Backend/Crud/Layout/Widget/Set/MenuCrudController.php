<?php

namespace Base\Controller\Backend\Crud\Layout\Widget\Set;

use Base\Controller\Backend\Crud\Layout\WidgetCrudController;
use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Set\Menu;
use Base\Entity\Layout\Widget\Slot;
use Base\Field\DiscriminatorField;
use Base\Field\SelectField;
use Base\Field\TranslationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class MenuCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        yield DiscriminatorField::new()->setTextAlign(TextAlign::RIGHT);
        yield SelectField::new('items')->setClass(Widget::class)->showVertical()->setColumns(6)->setFilter("^".Menu::class, "^".Slot::class);
        yield TranslationField::new('title')->setExcludedFields("content")->setFields([
            "title"   => ["form_type" => TextType::class],
            "excerpt" => ["form_type" => TextareaType::class]
        ]);

    }
}
