<?php

namespace Base\Controller\Dashboard\Crud\Layout\Attribute\Abstract;

class ArrayAttributeCrudController extends AbstractAttributeCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        return parent::configureFields($pageName, $callbacks);
    }
}
