<?php

namespace Base\Controller\Backend\Crud\Thread;

use Base\Controller\Backend\AbstractCrudController;
use Base\Field\SelectField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;

/**
 *
 */
class MentionCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function () {
            yield SelectField::new('mentioners')->setColumns(6);
            yield SelectField::new('mentionee')->setColumns(6)->setTextAlign(TextAlign::RIGHT);
            yield SelectField::new('thread')->setTextAlign(TextAlign::LEFT);
        }, $args);
    }
}
