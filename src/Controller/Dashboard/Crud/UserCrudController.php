<?php

namespace Base\Controller\Dashboard\Crud;

use Base\Config\CrudExtra;
use Base\Config\DashboardExtra;
use Base\Config\Extension;
use Base\Controller\Dashboard\AbstractCrudController;
use Base\Entity\User;
use Base\Field\AvatarField;

use Base\Field\PasswordField;
use Base\Field\ImpersonateField;
use Base\Field\LinkIdField;
use Base\Field\RoleField;
use Base\Field\BooleanField;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;

class UserCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() { return "fas fa-user"; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        yield AvatarField::new('avatar');
        foreach ( ($callbacks["avatar"] ?? $defaultCallback)() as $yield)
            yield $yield;
    
        yield EmailField::new('email');
        foreach ( ($callbacks["email"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield RoleField::new('roles')->allowMultipleChoices();
        foreach ( ($callbacks["roles"] ?? $defaultCallback)() as $yield)
            yield $yield;
            
        yield PasswordField::new('plainPassword')->onlyOnForms();
        foreach ( ($callbacks["plainPassword"] ?? $defaultCallback)() as $yield)
            yield $yield;
    
        yield BooleanField::new("isVerified")->onlyOnIndex()->withConfirmation();
        foreach ( ($callbacks["isVerified"] ?? $defaultCallback)() as $yield)
            yield $yield;
            
        yield DateTimeField::new('updatedAt')->onlyOnDetail();
        foreach ( ($callbacks["updatedAt"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield DateTimeField::new('createdAt')->onlyOnDetail();
        foreach ( ($callbacks["createdAt"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }

    public function configureActions(Actions $actions): Actions
    {
        $approveUser = Action::new('approve', 'Approve Users', 'fa fa-user-check')
            ->linkToCrudAction('approveUsers')
            ->addCssClass('btn btn-primary');

        return parent::configureActions($actions)
            ->addBatchAction($approveUser)->setPermission($approveUser, 'ROLE_SUPERADMIN');
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