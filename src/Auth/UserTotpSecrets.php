<?php

namespace Base\Auth;

use Omniauth\Contract\TotpSecrets;

/**
 * The authenticator applications' secrets: user.secret, as the column holds
 * it. Sealed at rest when the site sets omniauth's totp.secret_key (QA5): the
 * hosts' bridges then wrap this store in omniauth/otp's SealedTotpSecrets, so
 * the column holds "otp:v1:..." and its readers the base32 secret; a secret
 * kept in clear before (scheb/2fa's) is read as it is until the site's
 * migration rewrites it.
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
