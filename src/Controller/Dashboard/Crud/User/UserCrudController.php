<?php

namespace Base\Controller\Dashboard\Crud\User;
use Base\Service\BaseService;

use Base\Entity\User;
use Base\Field\AvatarField;
use Base\Field\Type\RoleType;

use Base\Field\PasswordField;
use Base\Field\ImpersonateField;
use Base\Field\LinkIdField;
use Base\Field\RoleField;
use Base\Field\BooleanField;

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

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function createEntity(string $entityFqcn)
    {
        return new User();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index', 'User Management')
            ->setDefaultSort(['id' => 'DESC'])
            ->setFormOptions(
                ['validation_groups' => ['new']], // Crud::PAGE_NEW
                ['validation_groups' => ['edit']] // Crud::PAGE_EDIT
            );
    }
    
    public function configureActions(Actions $actions): Actions
    {
        return $actions
        ->addBatchAction(Action::new('approve', 'Approve Users')
            ->linkToCrudAction('approveUsers')
            ->addCssClass('btn btn-primary')
            ->setIcon('fa fa-user-check'))

        ->add(Crud::PAGE_NEW,  Action::INDEX)
        ->add(Crud::PAGE_EDIT, Action::INDEX)

        ->setPermission(Action::NEW, 'ROLE_SUPERADMIN')
        ->setPermission(Action::EDIT, 'ROLE_SUPERADMIN')
        ->setPermission(Action::DELETE, 'ROLE_SUPERADMIN');
    }

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield LinkIdField::new('id')->hideOnForm();
        foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield AvatarField::new('avatar')->allowDelete();

        if ($this->isGranted('ROLE_SUPERADMIN')) yield ImpersonateField::new('username');
        else yield TextField::new('username');
        foreach ( ($callbacks["username"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield RoleField::new('roles')->allowMultipleChoices();
        foreach ( ($callbacks["roles"] ?? $defaultCallback)() as $yield)
            yield $yield;
            
        yield PasswordField::new('plainPassword')->onlyOnForms();
        foreach ( ($callbacks["plainPassword"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TextField::new('secret')->onlyOnForms();
        foreach ( ($callbacks["secret"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield EmailField::new('email')->hideOnIndex();
        foreach ( ($callbacks["email"] ?? $defaultCallback)() as $yield)
            yield $yield;
    
        yield BooleanField::new("isVerified")->onlyOnIndex()->withConfirmation();
        foreach ( ($callbacks["isVerified"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield BooleanField::new("isApproved")->onlyOnIndex()->withConfirmation();
        foreach ( ($callbacks["isApproved"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield BooleanField::new("isEnabled")->onlyOnIndex()->withConfirmation();
        foreach ( ($callbacks["isEnabled"] ?? $defaultCallback)() as $yield)
            yield $yield;
            
        yield DateTimeField::new('updatedAt')->onlyOnDetail();
        foreach ( ($callbacks["updatedAt"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield DateTimeField::new('createdAt')->onlyOnDetail();
        foreach ( ($callbacks["createdAt"] ?? $defaultCallback)() as $yield)
            yield $yield;
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

    public function approveUsers(BatchActionDto $batchActionDto)
    {
        $entityManager = $this->getDoctrine()->getManagerForClass($batchActionDto->getEntityFqcn());
        foreach ($batchActionDto->getEntityIds() as $id) {
            $user = $entityManager->find($batchActionDto->getEntityFqcn(), $id);
            $user->approve();
        }

        $entityManager->flush();

        return $this->redirect($batchActionDto->getReferrerUrl());
    }
}