<?php

namespace Base\EntitySubscriber;

use Base\Entity\User\Notification;
use Base\Entity\User\Token;
use Base\EntityDispatcher\Event\UserEvent;
use Base\Notifier\Notifier;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * What an account's owner is told when the account changes (UserEventDispatcher raises the
 * events): a registration asks to verify the address, an approval is confirmed, an account its
 * owner closes is said goodbye to, one reopened welcomed back. Each notification goes out with
 * its importance, through the site's channel policy (framework.notifier.channel_policy).
 *
 * The owner is told only of what they did themselves, or of what happened with nobody signed
 * in (a command, a worker): an administrator closing someone's account sends nothing.
 */
class UserSubscriber implements EventSubscriberInterface, ResetInterface
{
    /** The verification link's lifetime and the delay before another can be asked, as SecurityController's. */
    public const VERIFY_EMAIL_EXPIRY = 24 * 3600;
    public const VERIFY_EMAIL_THROTTLE = 3600;

    /** @var list<Token> the approval requests to revoke at the next flush */
    private array $revoke = [];

    public function __construct(
        protected Notifier $notifier,
        protected TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * The events come from inside a flush (UserEventDispatcher, postPersist/postUpdate): a token
     * revoked there leaves its user, but its orphan removal is forgotten when the flush ends
     * (postFlush included), and the next flush wrote the token with no user (userToken.user_id
     * is NOT NULL). Revoked as the next flush begins (the response's, EntityDispatcherFlushSubscriber),
     * it is removed by it.
     */
    public function preFlush(): void
    {
        $revoke = $this->revoke;
        $this->revoke = [];
        foreach ($revoke as $token) {
            $token->revoke();
        }
    }

    public function reset(): void
    {
        $this->revoke = [];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserEvent::REGISTER => ['onRegistration'],
            UserEvent::APPROVAL => ['onApproval'],
            UserEvent::ENABLED => ['onEnabling'],
            UserEvent::DISABLED => ['onDisabling'],
        ];
    }

    /** Whether someone other than $user is signed in. */
    private function byAnotherUser(object $user): bool
    {
        $signedIn = $this->tokenStorage->getToken()?->getUser();

        return null !== $signedIn && $signedIn !== $user;
    }

    public function onRegistration(UserEvent $event): void
    {
        $user = $event->getUser();
        if ($this->byAnotherUser($user) || $user->isVerified()) {
            return; // made by someone else (user:create mails its own), or nothing to verify (a social account)
        }

        $token = new Token('verify-email', self::VERIFY_EMAIL_EXPIRY, self::VERIFY_EMAIL_THROTTLE);
        $user->addToken($token);

        $this->notifier->verificationEmail($user, $token)->send('success');
    }

    public function onApproval(UserEvent $event): void
    {
        $user = $event->getUser();

        // Only an account that waited for an administrator (its "admin-approval" request).
        $request = $user->getValidToken('admin-approval');
        if (!$request) {
            return;
        }

        $this->revoke[] = $request;
        $this->notifier->userApprovalConfirmation($user)->send('success');
    }

    public function onEnabling(UserEvent $event): void
    {
        $user = $event->getUser();
        if ($this->byAnotherUser($user)) {
            return;
        }

        // The e-mail with the link came first (UserChecker, on a sign-in to a closed account):
        // the account reopened, its owner is told so.
        $notification = new Notification('accountWelcomeBack.success', [$user]);
        $notification->setUser($user);
        $notification->send('success');
    }

    public function onDisabling(UserEvent $event): void
    {
        $user = $event->getUser();
        if ($this->byAnotherUser($user)) {
            return;
        }

        $this->notifier->userAccountGoodbye($user)->send('success');
    }
}
