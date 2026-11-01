<?php

namespace Base\Auth\Listener;

use Base\Database\UnitOfWorks;
use Base\Entity\User;
use Base\Security\ConnectionTracker;
use Doctrine\ORM\EntityManagerInterface;
use Omniauth\Contract\Accounts;
use Omniauth\Event\SignedIn;
use Omniauth\Event\SignInFailed;
use Omnibridge\Persistence\UnitOfWork;

/**
 * The browsers an account signs in from (userConnection rows, through
 * Base\Security\UserTracker), on omniauth's events: what
 * Base\EntitySubscriber\ConnectionSubscriber does on Symfony's - a success
 * marks the browser's connection succeeded (made if it is new), a failure on
 * a known account marks it failed - the account the attempt had, or the one
 * of the identifier typed (a wrong password: Symfony's passport loaded it
 * too). Runs before WarnNewDevice, which looks for the connection a sign-in
 * made.
 */
class TrackConnections
{
    protected readonly UnitOfWork $unitOfWork;

    /** @param UnitOfWork|EntityManagerInterface $unitOfWork the entity manager is deprecated (until 6.0): adapted by UnitOfWorks */
    public function __construct(
        protected readonly ConnectionTracker $tracker,
        UnitOfWork|EntityManagerInterface $unitOfWork,
        protected readonly ?Accounts $accounts = null,
    ) {
        $this->unitOfWork = UnitOfWorks::of($unitOfWork);
    }

    public function onSignedIn(SignedIn $event): void
    {
        if (!$event->account instanceof User) {
            return;
        }
        $this->tracker->getCurrentConnection($event->account)?->markAsSucceeded();
        $this->unitOfWork->flush();
    }

    public function onSignInFailed(SignInFailed $event): void
    {
        $account = $event->account ?? (null !== $event->identifier ? $this->accounts?->byIdentifier($event->identifier) : null);
        if (!$account instanceof User) {
            return;
        }
        $this->tracker->getCurrentConnection($account)?->markAsFailed();
        $this->unitOfWork->flush();
    }

    /** @return array<class-string, string> omniauth's events and the method of this class each calls */
    public static function events(): array
    {
        return [SignedIn::class => 'onSignedIn', SignInFailed::class => 'onSignInFailed'];
    }
}
