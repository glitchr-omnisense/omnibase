<?php

namespace Base\Auth\Listener;

use App\Enum\UserRole;
use Base\Entity\User;
use Omniauth\Event\SignedIn;
use Omnibridge\Persistence\UnitOfWork;

/**
 * The account brought up to date at its sign-in, what
 * Omnibridge\Symfony\Omnibase\Security\LoginFormAuthenticator::onAuthenticationSuccess() does under
 * Symfony: a role the application's UserRole enum no longer knows dropped
 * from the account's own roles (never those of its groups), its time zone and
 * its language kept (the visitor's when it had none), its kick lifted.
 */
class UpdateAccount
{
    protected readonly UnitOfWork $unitOfWork;

    public function __construct(UnitOfWork $unitOfWork)
    {
        $this->unitOfWork = $unitOfWork;
    }

    public function onSignedIn(SignedIn $event): void
    {
        $user = $event->account;
        if (!$user instanceof User) {
            return;
        }
        $own = $user->getOwnRoles();
        $known = array_values(array_intersect($own, UserRole::getPermittedValues()));
        if (\count($known) !== \count($own)) {
            $user->setRoles($known);
        }
        $user->setTimezone();
        $user->setLocale();
        $user->kick(false);

        $this->unitOfWork->flush();
    }

    /** @return array<class-string, string> omniauth's events and the method of this class each calls */
    public static function events(): array
    {
        return [SignedIn::class => 'onSignedIn'];
    }
}
