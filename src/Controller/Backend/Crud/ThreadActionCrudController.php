<?php

namespace Base\Controller\Backend\Crud;

use Base\Controller\Backend\AbstractCrudController;
use Base\Enum\ThreadState;
use Base\Backend\Filter\DiscriminatorFilter;
use Base\Controller\Backend\AbstractDashboardController;
use Base\Entity\Thread;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;

class ThreadActionCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; }

    public function batchActionPublish(BatchActionDto $batchActionDto)
    {
        foreach ($batchActionDto->getEntityIds() as $id) {

            $thread = $this->entityManager->find($batchActionDto->getEntityFqcn(), $id);
            $thread->setState(ThreadState::PUBLISH);
        }

        $this->entityManager->flush();

        return $this->redirect($batchActionDto->getReferrerUrl());
    }

    public function batchActionDelete(BatchActionDto $batchActionDto)
    {
        foreach ($batchActionDto->getEntityIds() as $id) {

            $thread = $this->entityManager->find($batchActionDto->getEntityFqcn(), $id);
            $this->entityManager->remove($thread);
        }

        $this->entityManager->flush($thread);

        return $this->redirect($batchActionDto->getReferrerUrl());
    }

    public function configureFilters(Filters $filters): Filters { return $filters->add(DiscriminatorFilter::new('class', null, self::getEntityFqcn())); }
    public function configureActions(Actions $actions): Actions
    {
        $batchActionPublish = Action::new('batchActionPublish', '@'.AbstractDashboardController::TRANSLATION_DASHBOARD.'.action.batch_publish', 'fa fa-check')
            ->linkToCrudAction('batchActionPublish')
            ->addCssClass('btn btn-primary text-success');

        $batchActionDelete = Action::new('batchActionDelete', '@'.AbstractDashboardController::TRANSLATION_DASHBOARD.'.action.batch_delete', 'fa fa-trash')
            ->linkToCrudAction('batchActionDelete')
            ->addCssClass('btn btn-danger')
            ->displayIf(fn (Thread $entity) => ThreadState::PUBLISH !== $entity->getState());

        return parent::configureActions($actions)
        ->addBatchAction($batchActionDelete)->setPermission($batchActionDelete, 'ROLE_SUPERADMIN')
        ->addBatchAction($batchActionPublish)->setPermission($batchActionPublish, 'ROLE_SUPERADMIN');
    }

}
