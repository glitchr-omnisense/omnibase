<?php

namespace Base\Controller\Backoffice\Crud\Thread;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Filter\Filters;
use Base\Entity\Thread\Taxon;
use Base\Field\IconField;
use Base\Field\IdField;
use Base\Field\SelectField;
use Base\Field\SlugField;
use Base\Field\TranslationField;

/**
 * The taxonomy: categories that nest (a parent, children), each with a label,
 * a description and keywords per language. Threads are filed under them.
 */
class TaxonCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Taxon::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-sitemap';
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('slug')->add('parent');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TranslationField::new()->showOnIndex('label')->setFields([
            // The label of the site's language is asked; the others may stay
            // empty (an empty translation is dropped). `true` asked it in
            // every language, and no taxon could be made with one label.
            'label' => ['required' => fn (bool $required, string $locale, array $options): bool => $required],
            'description' => ['required' => false],
        ])->setColumns(12);
        yield SlugField::new('slug')->setTargetFieldName('translations.label')->setColumns(4);
        yield IconField::new('icon')->setRequired(false)->setColumns(4)->hideOnIndex();
        yield SelectField::new('parent')->setClass(static::getEntityFqcn())->setRequired(false)->setColumns(4);
        yield from $this->taxonFields($pageName);
    }

    /** What a kind of taxon adds (a market taxon: its store). */
    protected function taxonFields(string $pageName): iterable
    {
        return [];
    }
}
