<?php

namespace Base\Controller\Admin\Crud\Layout;

use Base\Controller\Admin\AbstractCrudController;
use Base\Field\DateTimePickerField;
use Base\Field\IconField;
use Base\Field\TranslationField;
use Base\Field\Type\WysiwygType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 *
 */
class TooltipCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {
            
            yield IconField::new('icon')->setColumns(3);
            yield TextField::new('title')->setTextAlign(TextAlign::RIGHT)->hideOnDetail()->hideOnForm();
            yield TranslationField::new()->setFields([
                "content" => ["form_type" => WysiwygType::class]
            ]);

            // NB: Consider adding an optional range datetime (instead of datetime picker) field to select the start and end date of the tooltip
            yield DateTimePickerField::new('startedAt')->onlyOnDetail();
            yield DateTimePickerField::new('endedAt')->onlyOnDetail();

            yield DateTimePickerField::new('updatedAt')->onlyOnDetail();
            yield DateTimePickerField::new('createdAt')->onlyOnDetail();

        }, $args);
    }
}
