<?php

namespace Base\Controller\Backend\Crud;

use Base\Field\DateTimePickerField;
use Base\Field\DiscriminatorField;

use Base\Field\SelectField;
use Base\Field\SlugField;
use Base\Field\StateField;
use Base\Field\TranslationField;
use Base\Field\Type\QuillType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ThreadCrudController extends ThreadActionCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function() {

            yield DiscriminatorField::new('class')->hideOnForm()->showColumnLabel();
            yield TextField::new('title')->setTextAlign(TextAlign::RIGHT)->hideOnDetail()->hideOnForm();
            yield SelectField::new('owners')->showFirst()->setTextAlign(TextAlign::LEFT);
            yield StateField::new('state')->setColumns(6);

            yield DateTimePickerField::new('publishedAt')->setFormat("dd MMM yyyy");

            yield SlugField::new('slug')->setTargetFieldName("translations.title");
            yield TranslationField::new()->setFields([
                "excerpt" => ["form_type" => TextareaType::class],
                "content" => ["form_type" => QuillType::class]
            ]);

            yield DateTimePickerField::new('updatedAt')->onlyOnDetail();
            yield DateTimePickerField::new('createdAt')->onlyOnDetail();

        }, $args);
    }
}
