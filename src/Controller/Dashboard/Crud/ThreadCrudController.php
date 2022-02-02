<?php

namespace Base\Controller\Dashboard\Crud;

use Base\Controller\Dashboard\AbstractCrudController;
use Base\Enum\ThreadState;
use Base\Field\DateTimePickerField;
use Base\Field\DiscriminatorField;
use Base\Field\IdField;

use Base\Field\SelectField;
use Base\Field\SlugField;
use Base\Field\StateField;
use Base\Field\TranslationField;
use Base\Field\Type\QuillType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\TextAlign;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

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
                ->setPermission($approveThread, 'ROLE_EDITOR');
    }

    public function configureFields(string $pageName, ...$args): iterable
    {
        return parent::configureFields($pageName, function() {
            
            yield IdField::new('id')->hideOnForm();

            yield DiscriminatorField::new('id')->hideOnForm()->showColumnLabel();
            yield TextField::new('title')->setTextAlign(TextAlign::RIGHT)->hideOnDetail()->hideOnForm();
            yield SelectField::new('owners')->showFirst()->setTextAlign(TextAlign::LEFT);
            yield StateField::new('state')->setColumns(6);
            
            yield DateTimePickerField::new('publishedAt')->setFormat("dd MMM yyyy");

            yield SlugField::new('slug')->setTargetFieldName("translations.title");
            yield TranslationField::new()->setFields([
                "excerpt" => ["form_type" => TextareaType::class],
                "content" => ["form_type" => QuillType::class]
            ]);

            yield DateTimePickerField::new('updatedAt')->onlyOnDetail();
            yield DateTimePickerField::new('createdAt')->onlyOnDetail();

        }, $args);
    }

    public function approveThreads(BatchActionDto $batchActionDto)
    {
        foreach ($batchActionDto->getEntityIds() as $id) {
            $thread = $this->entityManager->find($batchActionDto->getEntityFqcn(), $id);
            $thread->setState(ThreadState::PUBLISH);
        }

        $this->entityManager->flush();

        return $this->redirect($batchActionDto->getReferrerUrl());
    }
}