<?php

namespace Base\Auth\Listener;

use App\Enum\UserRole;
use Base\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Omniauth\Event\SignedIn;

/**
 * The account brought up to date at its sign-in, what
 * Base\Security\LoginFormAuthenticator::onAuthenticationSuccess() does under
 * Symfony: a role the application's UserRole enum no longer knows dropped
 * from the account's own roles (never those of its groups), its time zone and
 * its language kept (the visitor's when it had none), its kick lifted.
 */
class UpdateAccount
{
    public function __construct(protected readonly EntityManagerInterface $entityManager)
    {
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

        $this->entityManager->flush();
    }

    /** @return array<class-string, string> omniauth's events and the method of this class each calls */
    public static function events(): array
    {
        return [SignedIn::class => 'onSignedIn'];
    }
}
