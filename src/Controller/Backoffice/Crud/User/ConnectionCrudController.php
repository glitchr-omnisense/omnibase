<?php

namespace Base\Controller\Backoffice\Crud\User;

use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use Base\Admin\Controller\AbstractCrudController;
use Base\Field\AssociationField;
use Base\Field\DateTimeField;
use Base\Field\IdField;
use Base\Field\IntegerField;
use Base\Field\SelectField;
use Base\Field\TextField;
use Base\Admin\Filter\Filters;
use Base\Entity\User;
use Base\Entity\User\Connection;
use Base\Enum\ConnectionState;

/**
 * Read-only login audit log (who signed in/failed to, from where, when) -
 * an admin never creates or edits a Connection by hand, entries are written
 * automatically by ConnectionSubscriber on every login attempt.
 */
class ConnectionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Connection::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-right-to-bracket';
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $states = [ConnectionState::REQUESTED, ConnectionState::SUCCEEDED, ConnectionState::FAILED, ConnectionState::CLOSED];

        return $filters
            ->add('user')
            ->add(\Base\Admin\Filter\Filter::new('state', 'State')->asChoice(array_combine($states, $states)))
            ->add('createdAt');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield AssociationField::new('user')->setColumns(4);
        yield AssociationField::new('impersonator')->setColumns(4)->hideOnIndex();
        yield SelectField::new('state')->setColumns(2)->setEnumClass(ConnectionState::class)->setChoices([
            ConnectionState::REQUESTED,
            ConnectionState::SUCCEEDED,
            ConnectionState::FAILED,
            ConnectionState::CLOSED,
        ]);
        yield IntegerField::new('loginAttempts', 'Attempts')->setColumns(2);
        yield TextField::new('locale')->setColumns(2)->hideOnIndex();
        yield TextField::new('agent')->hideOnIndex();
        yield DateTimeField::new('createdAt')->setColumns(3);
        yield DateTimeField::new('updatedAt')->hideOnIndex()->setColumns(3);
    }
}
