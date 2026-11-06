<?php

namespace Base\Auth\Account;

use App\Entity\User\Notification;
use Omnibridge\Contract\Access;

/**
 * The pages for visitors only - the sign-up, the forgotten password and its link -, written once for every
 * host (plan "omnibase agnostic", step F5e, slice 5; formerly SecurityController::Register() and
 * ResetPasswordRequest()/Response(), and the twin's AccountPages::register() again): an account signed in
 * (and kept) is told so and sent to its profile.
 */
final class AlreadySignedIn
{
    public const PROFILE_ROUTE = 'user_profile';

    public function __construct(private readonly Access $access)
    {
    }

    public function check(): ?Outcome
    {
        $user = $this->access->getUser();
        if (null === $user || (method_exists($user, 'isPersistent') && !$user->isPersistent())) {
            return null;
        }

        (new Notification('login.already'))->send('warning');

        return Outcome::route(self::PROFILE_ROUTE);
    }
}
