<?php

namespace Base\Controller\Backend\Crud\Layout\Attribute\Adapter;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class HyperpatternAdapterCrudController extends AbstractAdapterCrudController
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
