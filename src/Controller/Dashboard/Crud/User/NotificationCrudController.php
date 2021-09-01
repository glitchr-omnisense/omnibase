<?php

namespace Base\Controller\Dashboard\Crud\User;
use Base\Service\BaseService;

use App\Entity\User\Notification;
use Base\Field\Type\RoleType;

use Base\Field\PasswordField;
use Base\Field\ImpersonateField;
use Base\Field\LinkIdField;
use Base\Field\RoleField;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;

class NotificationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Notification::class;
    }

    public function createEntity(string $entityFqcn)
    {
        return new Notification();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'Notification Management')
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
        ->setPermission(Action::EDIT, 'ROLE_SUPERADMIN')
        ->setPermission(Action::DELETE, 'ROLE_SUPERADMIN');
    }

    public function configureFields(string $pageName, callable $callbackAfterId = null): iterable
    {
        yield LinkIdField::new('id')->hideOnForm();
        if($callbackAfterId) $callbackAfterId();

        if ($this->isGranted('ROLE_SUPERADMIN')) yield ImpersonateField::new('username');
        else yield TextField::new('username');
        yield TextField::new('firstname');
        yield TextField::new('lastname');

        yield PasswordField::new('plainPassword')->onlyOnForms();
        yield TextField::new('secret')->onlyOnForms();

        yield EmailField::new('email')->hideOnIndex();

        yield RoleField::new('roles')->allowMultipleChoices();

        yield BooleanField::new("isVerified")->onlyOnIndex();
        yield BooleanField::new("isValid")->onlyOnIndex();
        yield DateTimeField::new('updatedAt')->onlyOnDetail();
        yield DateTimeField::new('createdAt')->onlyOnDetail();
    }


     public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('username')
            ->add('firstname')
            ->add('lastname')
            ->add('roles')
            ->add('email')
        ;
    }
}
