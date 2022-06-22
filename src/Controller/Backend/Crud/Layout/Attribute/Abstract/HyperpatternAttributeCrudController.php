<?php

namespace Base\Controller\Backend\Crud\Layout\Attribute\Abstract;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class HyperpatternAttributeCrudController extends AbstractAttributeCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, ["code" =>
            function() {

                yield TextField::new('pattern')->setColumns(4)->hideOnIndex();

            }], $args);
    }
}
