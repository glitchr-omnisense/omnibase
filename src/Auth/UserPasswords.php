<?php

namespace Base\Auth;

use Omniauth\Contract\Passwords;

/**
 * The password hashes: user.password, as Symfony's "auto" hasher wrote them
 * (bcrypt, cost 13 on lesringostars); omniauth's PasswordMethod checks them
 * with symfony/password-hasher under any host and hands back a rehash.
 */
class UserPasswords extends UserStore implements Passwords
{
    public function hash(object $account): ?string
    {
        $hash = $this->user($account)->getPassword();

        return null === $hash || '' === $hash ? null : $hash;
    }

    public function update(object $account, string $hash): void
    {
        $this->user($account)->setPassword($hash);
        $this->save();
    }
}
