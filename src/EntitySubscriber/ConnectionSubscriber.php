<?php

namespace Base\EntitySubscriber;

use App\Entity\User;
use Base\Enum\ConnectionState;
use Base\Security\SignInGuard;
use Base\Security\UserTracker;
use Base\Database\UnitOfWorks;
use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;


class ConnectionSubscriber implements EventSubscriberInterface
{
    /**
     * @var UserTracker
     */
    protected UserTracker $userTracker;

    /**
     * The entity manager when one was given (deprecated until 6.0: the unit of work below writes).
     *
     * @var EntityManagerInterface|null
     */
    protected ?EntityManagerInterface $entityManager = null;

    /**
     * Where the connections are written: omnisense/omnibridge's unit of work, the persistence chosen's (plan
     * "omnibase agnostic", step F7d-3: UserTracker's own, so that what it records is what is flushed - on Eloquent
     * too); an entity manager is adapted (UnitOfWorks).
     */
    protected UnitOfWork $unitOfWork;

    public function __construct(UserTracker $userTracker, UnitOfWork|EntityManagerInterface $unitOfWork)
    {
        $this->userTracker = $userTracker;
        $this->unitOfWork = UnitOfWorks::of($unitOfWork);
        if ($unitOfWork instanceof EntityManagerInterface) {
            $this->entityManager = $unitOfWork;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['onCheckPassport', 10],

            LoginSuccessEvent::class => ['onLoginSuccess', -65],
            LoginFailureEvent::class => ['onLoginFailure'],
            LogoutEvent::class => ['onLogout'],
        ];
    }

    public function onCheckPassport(CheckPassportEvent $event)
    {
        $passport = $event->getPassport();
        if(!$passport) return;
        // omniauth's sign-ins are Base\Auth\Listener\TrackConnections's, on its own events.
        if (SignInGuard::byOmniauth($passport)) return;

        $user = $passport->getUser();
        if (!$user instanceof User) return;

        // Looked up, not created: the credentials are not checked yet, and a
        // row per attempt was made (and flushed) before they were. A failure
        // is recorded by onLoginFailure, a success by onLoginSuccess.
        $this->userTracker->getCurrentConnection($user, false);
    }

    public function onLoginFailure(LoginFailureEvent $event)
    {
        $passport = $event->getPassport();
        if(!$passport) return;
        if (SignInGuard::byOmniauth($passport)) return;

        // The passport loads its user here, and may have none to give: an
        // identifier nobody has (UserNotFoundException), or an attempt refused
        // before the passport was given its loader - Symfony's login throttling
        // (too many attempts) refuses first of all, and UserBadge::getUser() then
        // throws a LogicException, which turned the refusal into a 500.
        $user = null;
        try { $user = $passport->getUser(); }
        catch(UserNotFoundException|\LogicException $e) { }
        if (!$user instanceof User) return;
        
        $connection = $this->userTracker->getCurrentConnection($user);
        $connection->markAsFailed();

        $this->unitOfWork->flush();
    }

    public function onLoginSuccess(LoginSuccessEvent $event)
    {
        if (SignInGuard::byOmniauth($event->getPassport())) return;
        $user = $event->getUser();
        if (!$user instanceof User) return;

        $connection = $this->userTracker->getCurrentConnection($user);
        $connection->markAsSucceeded();

        $this->unitOfWork->flush();
    }

    public function onLogout(LogoutEvent $event)
    {
        $token = $event->getToken();
        $user = $token ? $token->getUser() : null;
        if (!$user instanceof User) return;

        $connection = $this->userTracker->getCurrentConnection($user, false);
        if($connection) $connection->markAsLogout();
        
        $this->unitOfWork->flush();
    }
}