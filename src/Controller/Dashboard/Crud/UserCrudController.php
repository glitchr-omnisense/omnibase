<?php

namespace Base\Controller\Dashboard\Crud;

use Base\Config\Extension;
use Base\Controller\Dashboard\AbstractCrudController;
use Base\Controller\Dashboard\AbstractDashboardController;
use Base\Field\AvatarField;

use Base\Field\PasswordField;
use Base\Field\RoleField;
use Base\Field\BooleanField;
use Base\Field\DiscriminatorField;
use Base\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;

class UserCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

    public function configureExtensionWithResponseParameters(Extension $extension, KeyValueStore $responseParameters): Extension
    {
        if($entity = $this->getEntity()) {

            $extension->setImage($entity->getAvatar());

            $userClass = "user.".mb_strtolower(camel_to_snake(class_basename($entity)));
            $entityLabel = $this->translator->trans($userClass.".singular", [], AbstractDashboardController::TRANSLATION_ENTITY);
            if($entityLabel == $userClass.".singular") $entityLabel = null;
            else $extension->setTitle(mb_ucwords($entityLabel));

            $entityLabel = $entityLabel ?? $this->getCrud()->getAsDto()->getEntityLabelInSingular() ?? "";
            $entityLabel = !empty($entityLabel) ? mb_ucwords($entityLabel) : "";
            
            $impersonate = null;
            if($this->isGranted("ROLE_SUPERADMIN") && $this->getCrud()->getAsDto()->getCurrentAction() != "new") {
                $impersonate = $entity->getUserIdentifier();
                $impersonate = '<a class="impersonate" href="'.$this->getContext()->getRequest()->getRequestUri().'&_switch_user='.$impersonate.'"><i class="fa fa-fw fa-user-secret"></i></a>';
            }

            if($this->getCrud()->getAsDto()->getCurrentAction() == "new") $extension->setTitle($entityLabel);
            else {
                $extension->setTitle($entity.$impersonate);
                $extension->setText($entityLabel." #".$entity->getId()." | ".$this->translator->trans("@dashboard.crud.user.since", [$entity->getCreatedAt()->format("Y")])); 
            }
        }
        
        return $extension;
    }

    public function configureFilters(Filters $filters): Filters 
    { 
        return $filters->add('roles'); 
    }
    
    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        return parent::configureFields($pageName, [

            "id" => function() use ($defaultCallback, $callbacks) {
    

                yield AvatarField::new('avatar')->hideOnDetail();
                foreach ( ($callbacks["avatar"] ?? $defaultCallback)() as $yield)
                    yield $yield;

                yield RoleField::new('roles')->setColumns(5)->allowMultipleChoices(true);
                foreach ( ($callbacks["roles"] ?? $defaultCallback)() as $yield)
                    yield $yield;
                    
                yield EmailField::new('email')->setColumns(5);
                foreach ( ($callbacks["email"] ?? $defaultCallback)() as $yield)
                    yield $yield;

                yield BooleanField::new("isApproved")->withConfirmation();
                foreach ( ($callbacks["isApproved"] ?? $defaultCallback)() as $yield)
                    yield $yield;

                yield PasswordField::new('plainPassword')->onlyOnForms()->setColumns(6);
                foreach ( ($callbacks["plainPassword"] ?? $defaultCallback)() as $yield)
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
        $approveUser = Action::new('approve', 'Approve', 'fa fa-user-check')
            ->linkToCrudAction('approveUsers')
            ->addCssClass('btn btn-primary');

        return parent::configureActions($actions)

            ->addBatchAction($approveUser)
            ->setPermission($approveUser, 'ROLE_SUPERADMIN');
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