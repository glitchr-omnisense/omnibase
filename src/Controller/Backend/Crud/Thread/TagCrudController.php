<?php

namespace Base\Controller\Backend\Crud\Thread;

use Base\Controller\Backend\AbstractCrudController;
use Base\Field\DiscriminatorField;
use Base\Field\TranslationField;
use Base\Field\Type\SelectType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;

/**
 *
 */
class TagCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {
            yield DiscriminatorField::new('class')->hideOnForm()->showColumnLabel();
            yield TranslationField::new()->setFields([
                'label' => [],
                'keywords' => ['form_type' => SelectType::class, 'tags' => [',', ';'], 'required' => false],
            ])->setTextAlign(TextAlign::RIGHT)->hideOnDetail();
        });
    }
}
