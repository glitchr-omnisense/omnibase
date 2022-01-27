<?php

namespace Base\Controller\Dashboard\Crud\Layout\Widget;

use Base\Field\TranslationField;

use Base\Controller\Dashboard\Crud\Layout\WidgetCrudController;
use Base\Entity\Layout\Attribute\Abstract\HyperpatternAttribute;
use Base\Field\AttributeField;
use Base\Field\Type\QuillType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class LinkCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, ["id" => function () {

            yield AttributeField::new('hyperlink')->setClass(HyperpatternAttribute::class);
            yield TranslationField::new('title')->setFields([
                "title" => [],
                "excerpt" => ["form_type" => TextareaType::class],
                "content" => ["form_type" => QuillType::class],
            ]);
        }], $args);
    }
}
