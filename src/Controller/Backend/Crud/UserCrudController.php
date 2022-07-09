<?php

namespace Base\Controller\Backend\Crud;

use Base\Backend\Config\Extension;
use Base\Controller\Backend\AbstractCrudController;
use Base\Field\AvatarField;

use Base\Field\PasswordField;
use Base\Field\RoleField;
use Base\Field\BooleanField;

use Base\Field\EmailField;
use Base\Service\Translator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;

class UserCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function configureExtensionWithResponseParameters(Extension $extension, KeyValueStore $responseParameters): Extension
    {
        if($entity = $this->getEntity()) {

            $extension->setImage($entity->getAvatar());

            $userClass = "user.".mb_strtolower(camel2snake(class_basename($entity)));
            $entityLabel = $this->translator->transQuiet($userClass.".".Translator::NOUN_SINGULAR, [], Translator::DOMAIN_ENTITY);
            if($entityLabel) $extension->setTitle(mb_ucwords($entityLabel));

            $entityLabel ??= $this->getCrud()->getAsDto()->getEntityLabelInSingular() ?? "";
            $entityLabel   = $entityLabel ? mb_ucwords($entityLabel) : "";

            $impersonate = null;
            if($this->isGranted("ROLE_EDITOR") && $this->getCrud()->getAsDto()->getCurrentAction() != "new") {
                $impersonate = $entity->getUserIdentifier();
                $impersonate = '<a class="impersonate" href="'.$this->getContext()->getRequest()->getRequestUri().'&_switch_user='.$impersonate.'"><i class="fa fa-fw fa-user-secret"></i></a>';
            }

            if($this->getCrud()->getAsDto()->getCurrentAction() == "new") $extension->setTitle($entityLabel);
            else {
                $extension->setTitle($entity.$impersonate);
                $extension->setText($entityLabel." #".$entity->getId()." | ".$this->translator->trans("crud.user.since", [$entity->getCreatedAt()->format("Y")], Translator::DOMAIN_BACKEND));
            }
        }

        return $extension;
    }

    public function configureFilters(Filters $filters): Filters { return $filters->add('roles'); }
    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function() {

            yield BooleanField::new("isApproved")->withConfirmation()->showInline()->setColumns(3);
            yield FormField::addRow()->setColumns(9);
            yield AvatarField::new('avatar')->hideOnDetail()->setCropper();

            yield FormField::addRow()->setColumns(2);
            yield RoleField::new('roles')->setColumns(5);
            yield EmailField::new('email')->setColumns(5);

            yield FormField::addRow()->setColumns(2);
            yield PasswordField::new('plainPassword')->onlyOnForms()->allowEmpty()->setColumns(10)->showInline(false)->setRepeater(true)->setRevealer(true);

            yield DateTimeField::new('activeAt')->hideOnForm();
            yield DateTimeField::new('updatedAt')->onlyOnDetail();
            yield DateTimeField::new('createdAt')->onlyOnDetail();

        }, $args);
    }

    public function configureActions(Actions $actions): Actions
    {
        $approveUser = Action::new('approve', 'Approve', 'fa fa-user-check')
            ->linkToCrudAction('approveUsers')
            ->addCssClass('btn btn-primary');

        return parent::configureActions($actions)

            ->addBatchAction($approveUser)
            ->setPermission($approveUser, 'ROLE_EDITOR');
    }

    public function approveUsers(BatchActionDto $batchActionDto)
    {
        foreach ($batchActionDto->getEntityIds() as $id) {
            $user = $this->entityManager->find($batchActionDto->getEntityFqcn(), $id);
            $user->approve();

            $this->entityManager->flush($user);
        }

        return $this->redirect($batchActionDto->getReferrerUrl());
    }
}
