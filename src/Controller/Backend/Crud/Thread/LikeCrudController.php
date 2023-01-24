<?php

namespace Base\Controller\Backend\Crud\Thread;

use Base\Controller\Backend\AbstractCrudController;
use Base\Field\IconField;
use Base\Field\SelectField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;

class LikeCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() : ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function() {

            yield IconField::new('icon');
            yield SelectField::new('user')->onlyOnIndex()->setTextAlign(TextAlign::RIGHT);
            yield SelectField::new('thread')->onlyOnIndex()->setTextAlign(TextAlign::LEFT);

        }, $args);
    }
}