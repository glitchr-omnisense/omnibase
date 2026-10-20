<?php

namespace Base\Auth;

use Omniauth\Contract\TotpSecrets;

/**
 * The authenticator applications' secrets, base32: user.secret, as scheb/2fa
 * kept them (still in clear: their encryption is a step of its own, QA5,
 * through omniauth/totp's SealedTotpSecrets).
 */
class UserTotpSecrets extends UserStore implements TotpSecrets
{
    public function secret(object $account): ?string
    {
        $secret = $this->user($account)->getTotpSecret();

        return \is_string($secret) && '' !== $secret ? $secret : null;
    }

    public function enable(object $account, string $secret): void
    {
        $this->user($account)->setTotpSecret($secret);
        $this->save();
    }

    public function disable(object $account): void
    {
        $this->user($account)->setTotpSecret(null);
        $this->save();
    }
}
