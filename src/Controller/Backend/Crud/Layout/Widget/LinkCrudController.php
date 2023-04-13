<?php

namespace Base\Controller\Backend\Crud\Layout\Widget;

use Base\Field\TranslationField;

use Base\Controller\Backend\Crud\Layout\WidgetCrudController;
use Base\Entity\Layout\Attribute\Adapter\HyperpatternAdapter;
use Base\Entity\Layout\Attribute\Hyperlink;
use Base\Field\AttributeField;
use Base\Field\Type\EditorType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class LinkCrudController extends WidgetCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, ["id" => function () {
            yield AttributeField::new('hyperlink')->setClass(Hyperlink::class)->setFilter(HyperpatternAdapter::class);
            yield TranslationField::new('title')->setFields([
                "title" => [],
                "excerpt" => ["form_type" => TextareaType::class],
                "content" => ["form_type" => EditorType::class],
            ]);
        }], $args);
    }
}
