<?php

namespace Base\Auth\Account;

use Base\Entity\User;
use App\Entity\User\Notification;
use Base\Repository\Users;
use Omnibridge\Contract\Tokens;
use Omnibridge\Persistence\UnitOfWork;

/**
 * The sign-out page's rules, written once for every host (plan "omnibase agnostic", step F5e, slice 5;
 * formerly SecurityController::Logout(), whose confirmation omnibridge/laravel's SignIn::logoutPage() wrote
 * again). Signed in: where the host's sign-out asks a CSRF token, a request without the session's is asked to
 * confirm by a link that carries one - a link from another site, an <img>, a page kept from an ended session
 * signs nobody out -; with it, on to the host's sign-out. Signed out: the account that just left is told
 * goodbye (or that it was put out), and the visitor goes back where it was, the index from a secured page.
 * A prefetch (Base\Service\SpeculativeRequest) is answered with nothing by the host before any of this.
 */
final class SignOutPage
{
    public const TEMPLATE = 'security/logout.html.twig';

    public function __construct(
        private readonly ?Tokens $tokens,
        private readonly ?Users $users = null,
        private readonly ?UnitOfWork $unitOfWork = null,
    ) {
    }

    /** Whether a signed-in visitor is asked to confirm: the host checks a token ($csrf) and this one is not the session's. */
    public function confirms(bool $csrf, string $intent, mixed $token): bool
    {
        return $csrf && null !== $this->tokens && !$this->tokens->isValid($intent, \is_string($token) ? $token : null);
    }

    /**
     * The account that has just signed out (what the session kept of it: its id, or the account in an older
     * session): told goodbye, or that it was put out (its kick then forgotten); its expired tokens removed.
     */
    public function left(mixed $left): void
    {
        $id = \is_object($left) && method_exists($left, 'getId') ? $left->getId() : $left;
        $user = \is_scalar($id) ? $this->users?->find($id) : null;
        if (!$user instanceof User) {
            return;
        }

        if ($user->isKicked()) {
            $notification = new Notification('kickout', [$user]);
            $notification->setUser($user);
            $notification->send('warning');
            $user->kick(false);
        } else {
            (new Notification('logout.success', [$user]))->send('info');
        }

        $user->removeExpiredTokens();
        $this->unitOfWork?->flush();
    }

    /** Where a signed-out visitor goes: back where it was, the index's address from a secured page. */
    public function after(bool $secured): Outcome
    {
        return $secured ? Outcome::home() : Outcome::back();
    }
}
