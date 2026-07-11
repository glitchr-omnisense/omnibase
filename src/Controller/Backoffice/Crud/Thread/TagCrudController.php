<?php

namespace Base\Controller\Backoffice\Crud\Thread;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Field\IconField;
use Base\Admin\Field\IdField;
use Base\Admin\Field\NumberField;
use Base\Admin\Field\SlugField;
use Base\Admin\Field\TranslationField;
use Base\Admin\Filter\Filters;
use Base\Entity\Thread\Tag;

class TagCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tag::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-tag';
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('slug')->add('priority');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield SlugField::new('slug')->setColumns(3);
        yield IconField::new('icon')->setColumns(3)->hideOnIndex();
        yield NumberField::new('priority')->setColumns(3);
        yield TranslationField::new()->hideOnIndex()->setFields(['label' => []]);
    }
}
