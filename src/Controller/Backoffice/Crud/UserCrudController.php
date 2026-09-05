<?php

namespace Base\Controller\Backoffice\Crud;

use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Field\AvatarField;
use Base\Admin\Field\BooleanField;
use Base\Admin\Field\DateTimePickerField;
use Base\Admin\Field\DateField;
use Base\Admin\Field\DateTimeField;
use Base\Admin\Field\EmailField;
use Base\Admin\Field\IdField;
use Base\Admin\Field\PasswordField;
use Base\Admin\Field\RoleField;
use Base\Admin\Filter\Filters;
use Base\Entity\User;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-user';
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('email')->add('createdAt');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield BooleanField::new('isApproved')->setColumns(2);
        // setRequired(false): User::$avatar is `nullable: true`, but with
        // nothing declared the form guesser marks the upload required, so the
        // browser refused to submit /admin/users/new without a picture - a
        // user cannot be created at all. Same failure the tags/followers
        // fields had on Article. The label's red marker went with it.
        yield AvatarField::new('avatar')->setColumns(2)->hideOnDetail()->setRequired(false);
        // KNOWN ISSUE, not yet fixed: this binds to 'roles', which now
        // reads via User::getRoles() (own roles unioned with group
        // membership - see Group-based permissions). Saving this form
        // without touching the field re-persists the inflated,
        // group-inherited set back onto the user's own 'roles' column.
        // A property_path:'ownRoles' override was tried and reverted -
        // RoleField's choice guessing requires a real mapped Doctrine
        // column to introspect and threw on the synthetic property.
        // Needs a real fix (e.g. an explicit getter/setter data-mapper
        // override, or a dedicated OwnRoleType) before this is safe.
        yield RoleField::new('roles')->setColumns(8);
        yield EmailField::new('email')->setColumns(8);
        // The same picker every other date in this admin uses. DateField is a
        // native <input type="date">, which the browser renders in its own
        // locale - so on a French form this was the one field showing
        // mm/dd/yyyy, at its own height, next to fields styled by the app.
        // Date-only: no time on a birthdate.
        yield DateTimePickerField::new('birthdate')->hideOnIndex()->setColumns(4)
            ->setFormTypeOption('format', 'yyyy-MM-dd')
            ->setFormTypeOption('datetimepicker', ['enableTime' => false, 'dateFormat' => 'Y-m-d']);
        yield PasswordField::new('plainPassword')->onlyOnForms()->setRequired(false)->setColumns(12);
        yield DateTimeField::new('activeAt')->hideOnForm();
        yield DateTimeField::new('updatedAt')->onlyOnDetail();
        yield DateTimeField::new('createdAt')->onlyOnDetail();
    }
}
