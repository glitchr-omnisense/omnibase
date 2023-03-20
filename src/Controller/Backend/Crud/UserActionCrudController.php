<?php

namespace Base\Controller\Backend\Crud;

use Base\Controller\Backend\AbstractCrudController;
use Base\Controller\Backend\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;

class UserActionCrudController extends AbstractCrudController
{
    /**
     * @var EntityManager
     * */
    protected $entityManager;

    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    public function configureActions(Actions $actions): Actions
    {
        $batchActionApprove = Action::new('batchActionApprove', '@'.AbstractDashboardController::TRANSLATION_DASHBOARD.'.action.batch_approve', 'fa fa-user-check')
            ->linkToCrudAction('batchActionApprove')
            ->addCssClass('btn btn-primary text-success');

        return parent::configureActions($actions)
            ->addBatchAction($batchActionApprove)
            ->setPermission($batchActionApprove, 'ROLE_EDITOR');
    }

    public function batchActionApprove(BatchActionDto $batchActionDto)
    {
        foreach ($batchActionDto->getEntityIds() as $id) {
            $user = $this->entityManager->find($batchActionDto->getEntityFqcn(), $id);
            $user->approve();

            $this->entityManager->flush($user);
        }

        return $this->redirect($batchActionDto->getReferrerUrl());
    }
}
