<?php

namespace Base\Controller\Dashboard\Crud;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Field\AvatarField;

use Base\Field\PasswordField;
use Base\Field\RoleField;
use Base\Field\BooleanField;
use Base\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;

use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;

class UserCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        return parent::configureFields($pageName, [

            "id" => function() use ($defaultCallback, $callbacks) {

                yield AvatarField::new('avatar');
                foreach ( ($callbacks["avatar"] ?? $defaultCallback)() as $yield)
                    yield $yield;
            
                yield RoleField::new('roles')->allowMultipleChoices();
                foreach ( ($callbacks["roles"] ?? $defaultCallback)() as $yield)
                    yield $yield;
                    
                yield EmailField::new('email');
                foreach ( ($callbacks["email"] ?? $defaultCallback)() as $yield)
                    yield $yield;
        
                yield PasswordField::new('plainPassword')->onlyOnForms();
                foreach ( ($callbacks["plainPassword"] ?? $defaultCallback)() as $yield)
                    yield $yield;
            
                yield BooleanField::new("isVerified")->hideOnIndex()->withConfirmation();
                foreach ( ($callbacks["isVerified"] ?? $defaultCallback)() as $yield)
                    yield $yield;
                
                yield BooleanField::new("isApproved")->onlyOnIndex()->withConfirmation();
                foreach ( ($callbacks["isApproved"] ?? $defaultCallback)() as $yield)
                    yield $yield;
                        
                yield DateTimeField::new('updatedAt')->onlyOnDetail();
                foreach ( ($callbacks["updatedAt"] ?? $defaultCallback)() as $yield)
                    yield $yield;
        
                yield DateTimeField::new('createdAt')->onlyOnDetail();
                foreach ( ($callbacks["createdAt"] ?? $defaultCallback)() as $yield)
                    yield $yield;
            }
        ]);
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
        foreach ($batchActionDto->getEntityIds() as $id) {
            $user = $this->entityManager->find($batchActionDto->getEntityFqcn(), $id);
            $user->approve();
        }

        $this->entityManager->flush();

        return $this->redirect($batchActionDto->getReferrerUrl());
    }
}