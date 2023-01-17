<?php

namespace Base\Controller\Backend\Crud\Thread;

use Base\Controller\Backend\AbstractCrudController;
use Base\Field\IconField;
use Base\Field\SelectField;

class LikeCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() : ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function() {

            yield IconField::new('icon');
            yield SelectField::new('user')->onlyOnIndex();
            yield SelectField::new('thread')->onlyOnIndex();

        }, $args);
    }
}