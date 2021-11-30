<?php

namespace Base\Controller\Dashboard\Crud;

use Base\Controller\Dashboard\AbstractCrudController;

use Base\Enum\ThreadState;
use Base\Field\ImpersonateField;
use Base\Field\LinkIdField;
use Base\Field\StateField;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;

class ThreadCrudController extends AbstractCrudController
{
    public static function getPreferredIcon() { return "fas fa-box"; } 

    public function configureActions(Actions $actions): Actions
    {
        $approveThread = Action::new('approve', 'Approve', 'fa fa-user-check')
            ->linkToCrudAction('approveThreads')
            ->addCssClass('btn btn-primary');

        return parent::configureActions($actions)
                ->addBatchAction($approveThread)
                ->setPermission($approveThread, 'ROLE_SUPERADMIN');
    }

    public function configureFields(string $pageName, array $callbacks = []): iterable
    {
        $defaultCallback = function() { return []; };

        if ($this->isGranted('ROLE_SUPERADMIN')) yield ImpersonateField::new("id")->hideOnForm();
        else yield LinkIdField::new('id')->hideOnForm();
        foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield StateField::new('state');
        foreach ( ($callbacks["state"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield DateTimeField::new('updatedAt')->onlyOnDetail();
        foreach ( ($callbacks["updatedAt"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield DateTimeField::new('createdAt')->onlyOnDetail();
        foreach ( ($callbacks["createdAt"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }

    public function approveThreads(BatchActionDto $batchActionDto)
    {
        $entityManager = $this->getDoctrine()->getManagerForClass($batchActionDto->getEntityFqcn());
        foreach ($batchActionDto->getEntityIds() as $id) {
            $thread = $entityManager->find($batchActionDto->getEntityFqcn(), $id);
            $thread->setState(ThreadState::PUBLISHED);
        }

        $entityManager->flush();

        return $this->redirect($batchActionDto->getReferrerUrl());
    }
}