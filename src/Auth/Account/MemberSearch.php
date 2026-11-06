<?php

namespace Base\Auth\Account;

use Base\Repository\Users;

/**
 * The members' search, written once for every host (plan "omnibase agnostic", step F5e, slice 5b; formerly
 * UserSearchController::Main()): members are looked for by their username, where the application's account has
 * one - never by their e-mail address, which the page would show to anyone -, fifty at most, the banned and the
 * ghosts left out; no term, no one.
 */
final class MemberSearch
{
    public const LIMIT = 50;
    public const TEMPLATE = 'client/user/search.html.twig';

    public function __construct(private readonly Users $users)
    {
    }

    /** @return list<object> */
    public function find(?string $term): array
    {
        $term = trim((string) $term);
        if ('' === $term) {
            return [];
        }

        return array_values(array_filter($this->users->byUsernameContaining($term, self::LIMIT), static fn ($user) => !$user->isBanned() && !$user->isGhost()));
    }
}
