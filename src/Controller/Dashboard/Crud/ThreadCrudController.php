<?php

namespace Base\Controller\Dashboard\Crud;

use Base\Controller\Dashboard\AbstractCrudController;

use Base\Enum\ThreadState;
use Base\Field\DateTimePickerField;
use Base\Field\IdField;
use Base\Field\ImpersonateField;
use Base\Field\LinkIdField;
use Base\Field\SelectField;
use Base\Field\SlugField;
use Base\Field\StateField;
use Base\Field\TranslationField;
use Base\Field\Type\SelectType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;

class ThreadCrudController extends AbstractCrudController
{
    public static function getPreferredIcon(): ?string { return null; } 

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

        yield IdField::new('id')->hideOnForm();
        foreach ( ($callbacks["id"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield SelectField::new('authors')->showFirst();
        foreach ( ($callbacks["authors"] ?? $defaultCallback)() as $yield)
            yield $yield;
            
        yield StateField::new('state');
        foreach ( ($callbacks["state"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslationField::new('title');
        foreach ( ($callbacks["title"] ?? $defaultCallback)() as $yield)
            yield $yield;
        yield TranslationField::new("excerpt")->hideOnIndex();
        foreach ( ($callbacks["excerpt"] ?? $defaultCallback)() as $yield)
            yield $yield;
        yield TranslationField::new("content")->hideOnIndex();
        foreach ( ($callbacks["content"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield TranslationField::new();
        foreach ( ($callbacks[""] ?? $defaultCallback)() as $yield)
            yield $yield;
            
        yield DateTimePickerField::new('publishedAt');
        foreach ( ($callbacks["publishedAt"] ?? $defaultCallback)() as $yield)
            yield $yield;

        yield DateTimePickerField::new('updatedAt');
        foreach ( ($callbacks["updatedAt"] ?? $defaultCallback)() as $yield)
            yield $yield;
            
        yield DateTimePickerField::new('createdAt')->onlyOnDetail();
        foreach ( ($callbacks["createdAt"] ?? $defaultCallback)() as $yield)
            yield $yield;
    }

    public function approveThreads(BatchActionDto $batchActionDto)
    {
        foreach ($batchActionDto->getEntityIds() as $id) {
            $thread = $this->entityManager->find($batchActionDto->getEntityFqcn(), $id);
            $thread->setState(ThreadState::PUBLISHED);
        }

        $this->entityManager->flush();

        return $this->redirect($batchActionDto->getReferrerUrl());
    }
}