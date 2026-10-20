<?php

namespace Base\Auth;

use Omniauth\Contract\TrustedVersion;

/**
 * The trusted devices' version: user.trustedTokenVersion, the "vsn" claim of
 * the trusted_device cookie scheb/2fa wrote and omniauth writes alike.
 */
class UserTrustedVersion extends UserStore implements TrustedVersion
{
    public function version(object $account): int
    {
        return $this->user($account)->getTrustedTokenVersion();
    }

    public function revokeAll(object $account): void
    {
        $this->user($account)->invalidateTrustedDevices();
        $this->save();
    }
}
