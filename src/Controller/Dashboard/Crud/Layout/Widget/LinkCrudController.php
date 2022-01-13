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

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };
        return parent::configureFields($pageName, [
            "id" => function () use ($defaultCallback, $callbacks, $pageName) {

                yield AttributeField::new('hyperlink')->setClass(HyperpatternAttribute::class);
                foreach ( ($callbacks["hyperlink"] ?? $defaultCallback)() as $yield)
                    yield $yield;

                yield TranslationField::new()->showOnIndex('title')->setFields([
                    "title" => [],
                    "excerpt" => ["form_type" => TextareaType::class],
                    "content" => ["form_type" => QuillType::class],
                ]);
                foreach ( ($callbacks["title"] ?? $defaultCallback)() as $yield)
                    yield $yield;
        }]);
    }
}
