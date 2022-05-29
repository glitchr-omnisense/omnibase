<?php

namespace Base\Controller\Backoffice\Crud\Layout\Attribute\Abstract;

use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class TextAttributeCrudController extends AbstractAttributeCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, fn() => yield NumberField::new('length')->setColumns(12), $args);
    }
}
