<?php

namespace Base\Auth\Account;

use Base\Entity\User;
use Base\Enum\UserRole;
use Base\Repository\Users;
use Omnibridge\Contract\Access;

/**
 * Whose profile a page shows or edits, written once for every host (plan "omnibase agnostic", step F5e, slice 5;
 * formerly UserProfileController::Show() and Edit(), and the twin's AccountPages::profileEdit() again): an id,
 * that account (no such account: no such page); none (-1), the account signed in, kept - else the members'
 * search; another's profile is edited by an administrator only.
 */
final class ProfileAccess
{
    public const SEARCH_ROUTE = 'user_search';

    public function __construct(private readonly Access $access, private readonly Users $users)
    {
    }

    public function show(int $id = -1): User|Outcome
    {
        if ($id > 0) {
            return $this->users->oneById($id) ?? Outcome::missing('User not found.');
        }

        $user = $this->access->getUser();

        return $user instanceof User && $user->isPersistent() ? $user : Outcome::route(self::SEARCH_ROUTE);
    }

    public function edit(int $id = -1): User|Outcome
    {
        $user = $this->show($id);
        if ($user instanceof User && $user !== $this->access->getUser() && !$this->access->isGranted(UserRole::ADMIN)) {
            return Outcome::denied();
        }

        return $user;
    }
}
