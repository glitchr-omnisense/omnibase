<?php

namespace Base\Controller\Dashboard\Crud\Layout\Attribute;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\TranslationField;

use Base\Field\SelectField;
use Base\Field\Type\QuillType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class HyperlinkCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {

            yield SelectField::new('hyperpattern');
            yield TranslationField::new()->showOnIndex('title')->setFields([
                "title" => [],
                "excerpt" => ["form_type" => TextareaType::class],
                "content" => ["form_type" => QuillType::class],
            ]);
        },$args);
    }
}
