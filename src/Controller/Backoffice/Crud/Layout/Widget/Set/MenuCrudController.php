<?php

namespace Base\Controller\Backoffice\Crud\Layout\Widget\Set;

use Base\Controller\Backoffice\Crud\Layout\WidgetCrudController;
use Base\Entity\Layout\Widget;
use Base\Entity\Layout\Widget\Set\Menu;
use Base\Entity\Layout\Widget\Slot;
use Base\Field\DiscriminatorField;
use Base\Field\SelectField;
use Base\Field\SlugField;
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
        yield SelectField::new('widgets')->setClass(Widget::class)->showVertical()->setColumns(6)->setFilter("^".Menu::class, "^".Slot::class);
        yield SlugField::new('path')->setSeparator(".")->hideOnIndex()->setColumns(6)->setTargetFieldName("translations.title");
        yield TranslationField::new('title')->setExcludedFields("content")->setFields([
            "title"   => ["form_type" => TextType::class],
            "excerpt" => ["form_type" => TextareaType::class]
        ]);

    }
}
