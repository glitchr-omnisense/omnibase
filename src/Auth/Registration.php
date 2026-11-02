<?php

namespace Base\Auth;

use Base\Database\UnitOfWorks;
use Base\Entity\User;
use Base\Notifier\NotifierInterface;
use Base\Repository\Users;
use Base\Service\ParameterBagInterface;
use Omnibridge\Persistence\UnitOfWork;

/**
 * A sign-up once its form holds, the same on every host (plan "omnibase agnostic", step F5c):
 * the account approved or left to an administrator (base.user.register.autoapprove), its password,
 * a name when its class has a username the form left empty, verified at once when a verified
 * account signs up (a social connection), the administrators told (base.user.register.notify_admins),
 * then kept. Symfony's SecurityController::Register() signs the account in afterwards; another host
 * does it its own way.
 */
final class Registration
{
    public function __construct(
        private readonly ParameterBagInterface $parameters,
        private readonly UnitOfWork $unitOfWork,
        private readonly Users $users,
        private readonly ?NotifierInterface $notifier = null,
    ) {
    }

    /** The new account made ready and kept; $signedIn the account already signed in, if any. */
    public function register(User $user, ?string $plainPassword, ?User $signedIn = null): User
    {
        // An account might require to be verified by an admin
        $user->approve((bool) $this->parameters->get('base.user.register.autoapprove'));
        $user->setPlainPassword($plainPassword);
        $this->name($user);

        // Social account connection
        if (null !== $signedIn && $signedIn->isVerified()) {
            $user->verify($signedIn->isVerified());
        }

        if ($user->isVerified() && $this->parameters->get('base.user.register.notify_admins')) {
            $this->notifier?->sendUserApprovalRequest($user);
        }

        $this->unitOfWork->persist($user);
        $this->unitOfWork->flush();

        return $user;
    }

    /**
     * An application's User usually carries a username - a column of its own,
     * unique and not null - which the sign-up form does not ask for: the
     * account was inserted without one and the sign-up answered 500. It is
     * given the local part of its address, numbered when somebody has it
     * ("anne", "anne2"), as an invitation's or a demonstration's account is
     * named. A form that asks for a username (an application's own type)
     * keeps what was typed; a User without that column is left alone.
     */
    public function name(User $user): void
    {
        if (!method_exists($user, 'setUsername') || !method_exists($user, 'getUsername')) {
            return;
        }
        if (!UnitOfWorks::shapes($this->unitOfWork)->shape($user)->hasField('username') || '' !== trim((string) $user->getUsername())) {
            return;
        }

        $base = preg_replace('/[^\p{L}\p{N}._-]+/u', '', mb_strtolower((string) strstr($user->getEmail().'@', '@', true)));
        $base = mb_substr('' !== $base && null !== $base ? $base : 'user', 0, 200);

        $username = $base;
        for ($n = 2; $this->users->findOneBy(['username' => $username]); ++$n) {
            $username = $base.$n;
        }

        $user->setUsername($username);
    }
}
