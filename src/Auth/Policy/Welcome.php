<?php

namespace Base\Auth\Policy;

/**
 * The word of welcome a sign-in leaves (plan "omnibase agnostic", step F5e, slice 1): the key
 * omnibridge/symfony's SecuritySubscriber::onLoginSuccess() and omnibridge/laravel's WelcomeFlash each
 * chose, in @notifications, the account its {0}, a "success".
 *
 * - an account not yet saved (signed in by an identity provider): `login.social`;
 * - a verified account: `login.success.alien` where the host says it is not authenticated (Symfony's
 *   IS_AUTHENTICATED_FULLY/REMEMBERED refused), else `login.success.<period>.<active>` - the period of its
 *   last activity (morning 05-10 h, afternoon 12-15 h, evening 19-05 h, day otherwise: time_is_between()),
 *   "first" when that activity is not today's, "back" when it is (daydiff());
 * - nothing for any other account.
 */
final class Welcome
{
    public const DOMAIN = '@notifications';

    /** The key, without its domain; null when nothing is said. */
    public static function key(object $user, bool $authenticated = true): ?string
    {
        if (method_exists($user, 'isPersistent') && !$user->isPersistent()) {
            return 'login.social';
        }
        if (!method_exists($user, 'isVerified') || !$user->isVerified()) {
            return null;
        }
        if (!$authenticated) {
            return 'login.success.alien';
        }

        $activeAt = method_exists($user, 'getActiveAt') ? $user->getActiveAt() : null;
        $active = daydiff($activeAt) < 0 ? 'first' : 'back';
        if (time_is_between($activeAt, '05:00:00', '10:00:00')) {
            $period = 'morning';
        } elseif (time_is_between($activeAt, '12:00:00', '15:00:00')) {
            $period = 'afternoon';
        } elseif (time_is_between($activeAt, '19:00:00', '05:00:00')) {
            $period = 'evening';
        } else {
            $period = 'day';
        }

        return 'login.success.'.$period.'.'.$active;
    }
}
