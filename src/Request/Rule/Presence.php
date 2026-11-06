<?php

namespace Base\Request\Rule;

use Base\Entity\User;
use Omnibridge\Persistence\UnitOfWork;

/**
 * The presence of the account signed in (plan "omnibase agnostic", step F5e, slice 2; formerly
 * omnibridge/symfony's SecuritySubscriber and omnibridge/laravel's Middleware\Presence, each its copy): an
 * omnibase account not active lately (User::isActive()) is poked - its activeAt now - and written.
 *
 * Asked before the page is drawn, not after it: poked on the answer, the first page after a pause (a restored
 * session, a tab left open) showed its own reader offline, and online only on the next click. Asked once more
 * on the answer, for an account signed in during the request itself (a sign-up, a sign-in). The write keeps
 * the unit of work as it is (a flush, never a clear): this runs on every request, and an entity manager
 * cleared under the controller left the signed-in account detached from every row loaded after it.
 */
final class Presence
{
    public function __construct(private readonly ?UnitOfWork $unitOfWork = null)
    {
    }

    /** Whether the account was poked. */
    public function poke(mixed $user): bool
    {
        if (!$user instanceof User || $user->isActive()) {
            return false;
        }
        $user->poke(new \DateTime('now'));
        $this->unitOfWork?->flush();

        return true;
    }
}
