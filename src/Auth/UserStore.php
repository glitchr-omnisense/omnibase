<?php

namespace Base\Auth;

use Base\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * What omniauth's stores over omnibase's tables share (plan "omniauth",
 * §2.2, step AU4): Doctrine's entity manager - Symfony's, or the one a
 * Laravel host builds by hand on the same mapping -, the accounts as
 * omnibase's users, and a write kept at once (a method that returns has
 * written: a link issued is in the table before its e-mail leaves).
 *
 * Nothing of a framework: the Base\Auth\ classes load with omniauth, Doctrine
 * and the core alone (BareTest). The core only suggests omniauth; nothing here
 * is wired without it.
 */
abstract class UserStore
{
    public function __construct(protected readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * The account omniauth hands back, as omnibase's user - the entity
     * manager's own when the object given is not (an account read before the
     * manager was cleared): what is written is then what is flushed.
     */
    protected function user(object $account): User
    {
        if (!$account instanceof User) {
            throw new \InvalidArgumentException(\sprintf('omnibase\'s stores hold %s accounts, not %s.', User::class, $account::class));
        }
        if (null === $account->getId() || $this->entityManager->contains($account)) {
            return $account;
        }
        $managed = $this->entityManager->find($account::class, $account->getId());

        return $managed instanceof User ? $managed : $account;
    }

    protected function save(): void
    {
        $this->entityManager->flush();
    }
}
