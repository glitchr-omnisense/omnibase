<?php

namespace Base\Controller\Backoffice\Crud\Layout;

use Base\Admin\Controller\AbstractCrudController;
use Base\Field\BooleanField;
use Base\Field\IdField;
use Base\Field\SlugField;
use Base\Field\TranslationField;
use Base\Admin\Filter\Filters;
use Base\Entity\Layout\Setting;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class SettingCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Setting::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-sliders';
    }

    public function createEntity(string $entityFqcn): object
    {
        return new $entityFqcn('');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('path')->add('locked')->add('secure');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield SlugField::new('path')->showLeadingHash(false)->setColumns(6)->keep('_')->setSeparator('.');
        yield SlugField::new('bag')->showLeadingHash(false)->setColumns(6)->keep('_')->setSeparator('.')->hideOnIndex();
        yield BooleanField::new('locked')->setColumns(3);
        yield BooleanField::new('secure')->setColumns(3);
        yield TranslationField::new()->hideOnIndex()->setFields([
            'label' => [],
            'help' => ['form_type' => TextareaType::class, 'required' => false],
        ])->setExcludedFields('value');
    }
}
