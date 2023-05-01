<?php

namespace Base\Controller\Backend\Crud\Thread;

use Base\Controller\Backend\AbstractCrudController;
use Base\Field\SelectField;

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
            yield SelectField::new('target')->onlyOnIndex();
            yield SelectField::new('author')->onlyOnIndex();
            yield SelectField::new('thread')->onlyOnIndex();
        }, $args);
    }
}
