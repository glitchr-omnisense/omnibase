<?php

namespace Base\Controller\Backoffice\Crud\Layout\Attribute;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Filter\Filters;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;
use Base\Field\IconField;
use Base\Field\IdField;
use Base\Field\TextField;
use Base\Field\TranslationField;

/**
 * Every attribute adapter - the definitions behind scopes, rules, actions
 * and plain attributes ("scope-region", "rule-cart-total", a barcode
 * standard...): their code, icon and label. Each kind is created from its
 * own CRUD, which knows its settings (the list's "new" offers those kinds);
 * this list renames and re-icons them, and keeps those attributes still use.
 */
class AdapterCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AbstractAdapter::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-plug';
    }

    public function isDeletable(object $entity): bool
    {
        return 0 === \count($entity->getAttributes());
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('code');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('code')->setColumns(4);
        yield IconField::new('icon')->setColumns(4);
        yield TranslationField::new()->showOnIndex('label')->setFields([
            'label' => ['required' => true],
            'help' => ['required' => false],
        ])->setColumns(12);
        yield from $this->adapterFields($pageName);
    }

    /** The settings a kind of adapter adds. */
    protected function adapterFields(string $pageName): iterable
    {
        return [];
    }
}
