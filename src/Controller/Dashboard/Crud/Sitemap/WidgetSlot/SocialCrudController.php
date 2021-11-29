<?php

namespace Base\Controller\Dashboard\Crud\Sitemap\WidgetSlot;

use Base\Entity\Sitemap\WidgetSlot\Social;
use Base\Field\FontAwesomeField;
use Base\Field\LinkIdField;
use Base\Field\SlugField;
use Base\Field\TranslatableField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;

class SocialCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Social::class;
    }

    public function createEntity(string $entityFqcn)
    {
        $social = new Social();
        return $social;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Social media management')
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

        ->setPermission(Action::NEW, 'ROLE_SUPERADMIN')
        ->setPermission(Action::DELETE, 'ROLE_SUPERADMIN')
        ->setPermission(Action::EDIT, 'ROLE_SUPERADMIN');
    }

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield LinkIdField::new('id')->hideOnForm();
        foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TextField::new("urlPattern")->hideOnIndex();
        foreach ( ($callbacks["urlPattern"] ?? $defaultCallback)() as $yield)
            yield $yield;
            
        yield FontAwesomeField::new('icon');
        foreach ( ($callbacks["icon"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield SlugField::new('socialName')->setTargetFieldName("translations.label");
        foreach ( ($callbacks["socialName"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslatableField::new("label");
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters;
    }
}