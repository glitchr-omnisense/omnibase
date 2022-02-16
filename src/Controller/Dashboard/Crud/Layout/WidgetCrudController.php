<?php

namespace Base\Controller\Dashboard\Crud\Layout;

use Base\Field\TranslationField;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Entity\Layout\Widget\Page;
use Base\Field\DiscriminatorField;
use Base\Field\SelectField;
use Base\Field\Type\QuillType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class WidgetCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function createEntity(string $entityFqcn) { return new $entityFqcn(""); }
    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield DiscriminatorField::new("type")->setTextAlign(TextAlign::RIGHT);
            yield TranslationField::new('title')->setFields([
                "title"   => TextType::class,
                "excerpt" => TextareaType::class,
                "content" => QuillType::class,
            ]);

        }, $args);
    }
}