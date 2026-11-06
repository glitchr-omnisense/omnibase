<?php

namespace Base\Notifier;

use Base\Notifier\Abstract\BaseNotifierInterface;
use Base\Repository\Users;
use Omnibridge\Contract\Access;

/**
 * Who the profiler's test e-mail is written to, written once for every host (plan "omnibase agnostic", step F5e,
 * slice 5b; formerly ProfilerController::Email() and SendEmail()): the account signed in, else the technical
 * recipient's account, found by its address, else an account made for the e-mail only (never kept), its address
 * the technical recipient's, its username - where the application's account has one - the recipient's name.
 *
 * Fixed on the way: the account was looked for by the recipient's name, and a Query's results handed on as if they
 * were one account (a TypeError for a recipient written without a name).
 */
final class TestRecipient
{
    public function __construct(
        private readonly Access $access,
        private readonly BaseNotifierInterface $notifier,
        private readonly Users $users,
    ) {
    }

    public function recipient(): object
    {
        $user = $this->access->getUser();
        if (null !== $user) {
            return $user;
        }
        $mail = mailparse((string) $this->notifier->getTechnicalRecipient()?->getEmail());
        $email = (string) first(array_keys($mail));
        $user = '' !== $email ? $this->users->oneByEmail($email) : null;
        if (null !== $user) {
            return $user;
        }
        $user = new \App\Entity\User();
        // A username where the application's account has one (Base\Entity\User has none).
        if (method_exists($user, 'setUsername')) {
            $user->setUsername((string) first($mail));
        }

        return $user->setEmail($email);
    }
}
