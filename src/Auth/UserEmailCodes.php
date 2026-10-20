<?php

namespace Base\Auth;

use Omniauth\Contract\EmailCodes;

/**
 * The code by e-mail: user.emailAuthEnabled, and user.emailAuthCode holding,
 * by Marco's decision QA5, the code's keyed hash (omniauth's EmailCodeMethod
 * computes it) instead of the code scheb/2fa kept in clear; null once used or
 * turned off. A code scheb sent before the switch matches no hash: its owner
 * asks for another (it lived minutes).
 */
class UserEmailCodes extends UserStore implements EmailCodes
{
    public function enabled(object $account): bool
    {
        return $this->user($account)->isEmailAuthEnabled();
    }

    public function enable(object $account): void
    {
        $this->user($account)->setEmailAuthEnabled(true);
        $this->save();
    }

    public function disable(object $account): void
    {
        $user = $this->user($account);
        $user->setEmailAuthEnabled(false);
        $user->setEmailAuthCode(null);
        $this->save();
    }

    public function remember(object $account, string $hash): void
    {
        $this->user($account)->setEmailAuthCode($hash);
        $this->save();
    }

    public function remembered(object $account): ?string
    {
        $code = $this->user($account)->getEmailAuthCode();

        return null === $code || '' === $code ? null : $code;
    }

    public function forget(object $account): void
    {
        $this->user($account)->setEmailAuthCode(null);
        $this->save();
    }
}
