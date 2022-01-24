<?php

namespace Base\Controller\Dashboard\Crud\Layout\Attribute\Abstract;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use Base\Field\AssociationField;

class HyperpatternAttributeCrudController extends AbstractAttributeCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, [
            fn() => yield TextField::new('pattern')->onlyOnForms()->setColumns(12),
            "translations" => fn() => yield TextField::new('pattern')->hideOnForm()
        ], $args);
    }
}
