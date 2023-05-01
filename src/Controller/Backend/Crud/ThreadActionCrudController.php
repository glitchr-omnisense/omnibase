<?php

namespace Base\Controller\Backend\Crud;

use Base\Controller\Backend\AbstractCrudController;
use Base\Enum\ThreadState;
use Base\Backend\Filter\DiscriminatorFilter;
use Base\Controller\Backend\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 *
 */
class ThreadActionCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    /**
     * @param BatchActionDto $batchActionDto
     * @return RedirectResponse
     */
    public function batchActionPublish(BatchActionDto $batchActionDto)
    {
        foreach ($batchActionDto->getEntityIds() as $id) {
            $thread = $this->entityManager->find($batchActionDto->getEntityFqcn(), $id);
            $thread->setState(ThreadState::PUBLISH);
        }

        $this->entityManager->flush();

        return $this->redirect($batchActionDto->getReferrerUrl());
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(DiscriminatorFilter::new('class', null, self::getEntityFqcn()));
    }

    public function configureActions(Actions $actions): Actions
    {
        $batchActionPublish = Action::new('batchActionPublish', '@' . AbstractDashboardController::TRANSLATION_DASHBOARD . '.action.batch_publish', 'fa-solid fa-check')
            ->linkToCrudAction('batchActionPublish')
            ->addCssClass('btn btn-primary text-success');

        return parent::configureActions($actions)
            ->addBatchAction($batchActionPublish)->setPermission($batchActionPublish, 'ROLE_SUPERADMIN');
    }
}
