<?php

namespace Base\Auth;

use Base\Database\Repository\Entities;
use Base\Database\UnitOfWorks;
use Base\Entity\User;
use Omnibridge\Persistence\UnitOfWork;

/**
 * What omniauth's stores over omnibase's tables share (plan "omniauth",
 * §2.2, step AU4): the unit of work (plan "omnibase agnostic", step F2d:
 * omnisense/omnibridge's contract - Doctrine's under Symfony, or over the
 * entity manager a Laravel host builds by hand on the same mapping -), the
 * repositories through it, the accounts as omnibase's users, and a write kept
 * at once (a method that returns has written: a link issued is in the table
 * before its e-mail leaves).
 *
 * Nothing of a framework: the Base\Auth\ classes load with omniauth, Doctrine
 * and the core alone (BareTest). The core only suggests omniauth; nothing here
 * is wired without it.
 */
abstract class UserStore
{
    protected readonly UnitOfWork $unitOfWork;

    public function __construct(UnitOfWork $unitOfWork)
    {
        $this->unitOfWork = $unitOfWork;
    }

    /**
     * The repository of that entity class, as the unit of work's persistence keeps it.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return Entities<T>
     */
    protected function entities(string $class): Entities
    {
        return UnitOfWorks::repositories($this->unitOfWork)->of($class);
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
        if (null === $account->getId() || $this->unitOfWork->contains($account)) {
            return $account;
        }
        $managed = $this->entities($account::class)->find($account->getId());

        return $managed instanceof User ? $managed : $account;
    }

    protected function save(): void
    {
        $this->unitOfWork->flush();
    }
}
