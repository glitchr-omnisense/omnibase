<?php

namespace Base\Controller\Dashboard\Crud\Sitemap;

use Base\Entity\Sitemap\Widget\Attachment;
use Base\Entity\Sitemap\WidgetSlot;
use Base\Service\BaseService;

use Base\Entity\User;
use Base\Field\Type\RoleType;

use Base\Field\PasswordField;
use Base\Field\ImpersonateField;
use Base\Field\LinkIdField;
use Base\Field\RoleField;
use Base\Field\BooleanField;
use Base\Field\TranslatableField;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;

class WidgetSlotCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return WidgetSlot::class;
    }

    public function createEntity(string $entityFqcn)
    {
        return new WidgetSlot();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Widget Slots Management')
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
        ->setPermission(Action::EDIT, 'ROLE_ADMIN');
    }

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield LinkIdField::new('id')->hideOnForm();
        foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters;
    }
}