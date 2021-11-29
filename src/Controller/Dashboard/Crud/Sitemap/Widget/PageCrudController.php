<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\Widget;

use Base\Entity\Sitemap\Widget\Page;
use Base\Field\AvatarField;
use Base\Field\ImageField;
use Base\Field\TranslatableField;
use Base\Field\LinkIdField;
use Base\Field\SlugField;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;

class PageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    public function createEntity(string $entityFqcn)
    {
        return new Page();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Page management')
            ->setDefaultSort(['id' => 'DESC'])
            ->setFormOptions(
                ['validation_groups' => ['new']], // Crud::PAGE_NEW
                ['validation_groups' => ['edit']] // Crud::PAGE_EDIT
            );
    }
    
    public function configureActions(Actions $actions): Actions
    {
        return $actions

        ->add(Crud::PAGE_NEW,  Action::INDEX)
        ->add(Crud::PAGE_EDIT, Action::INDEX)

        ->setPermission(Action::NEW, 'ROLE_ADMIN')
        ->setPermission(Action::DELETE, 'ROLE_ADMIN')
        ->setPermission(Action::EDIT, 'ROLE_ADMIN');
    }

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield LinkIdField::new('id')->hideOnForm();
        foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield ImageField::new('thumbnail');

        yield SlugField::new('slug')->setTargetFieldName("translations.title");

        yield TranslatableField::new()->showOnIndex('title');
        foreach ( ($callbacks["title"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }

     public function configureFilters(Filters $filters): Filters
    {
        return $filters;
    }
}