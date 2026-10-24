<?php

namespace Base\Auth\Listener;

use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Notifier\Mail\SignInMails;
use Base\Repository\User\Connections;
use Base\Security\ConnectionTracker;
use Base\Service\SecurityPolicy;
use Omniauth\Contract\State;
use Omniauth\Event\SignedIn;

/**
 * A sign-in from a browser the account had not used (a connection this
 * sign-in made, TrackConnections running first): the e-mail "a new device"
 * when the administrator turned it on, and - on an account with no second
 * factor that has not declined it - the flag of the session that makes the
 * next page offer, once, to set up a code. Base\Subscriber\NewDeviceSubscriber's
 * rule on omniauth's SignedIn event: never at the account's very first
 * sign-in (nothing to compare with), nothing that blocks the sign-in.
 *
 * The session is omniauth's State (the host's session); whether the visitor
 * declined the offer is a cookie the host reads ($declined).
 */
class WarnNewDevice
{
    /** A connection made within this many seconds of the sign-in is one this sign-in made. */
    public const FRESH_SECONDS = 120;

    /**
     * @param \Closure(): bool $declined whether this browser said "no thanks" (SecurityPolicy::COOKIE_NEW_DEVICE_PROMPT_DISMISSED)
     * @param SignInMails|null $mails    the e-mail written and sent without Symfony's Notifier (a host without it: omnibridge/laravel); the Notifier's otherwise
     */
    public function __construct(
        protected readonly SecurityPolicy $securityPolicy,
        protected readonly ConnectionTracker $tracker,
        protected readonly Connections $connections,
        protected readonly ?State $state = null,
        protected readonly ?\Closure $declined = null,
        protected readonly ?SignInMails $mails = null,
    ) {
    }

    public function onSignedIn(SignedIn $event): void
    {
        $email = $this->securityPolicy->isNewDeviceEmailEnabled();
        $prompt = $this->securityPolicy->isNewDevicePromptEnabled() && null !== $this->state;
        $user = $event->account;
        if ((!$email && !$prompt) || !$user instanceof User) {
            return;
        }

        $connection = $this->tracker->getCurrentConnection($user, false);
        if (null === $connection?->getCreatedAt() || time() - $connection->getCreatedAt()->getTimestamp() > self::FRESH_SECONDS) {
            return; // none, or a browser this account has used before
        }
        if ($this->connections->countFor($user) < 2) {
            return; // its very first sign-in
        }

        if ($email && null !== $this->mails) {
            $this->mails->newDevice($user, $connection);
        } elseif ($email) {
            $notification = new Notification('newDevice.subject', [$user]);
            $notification->setUser($user);
            $notification->setHtmlTemplate(SignInMails::NEW_DEVICE_TEMPLATE, SignInMails::newDeviceParameters($user, $connection));
            $notification->send('email');
        }
        if ($prompt && !$this->securityPolicy->hasSecondFactor($user) && !(null !== $this->declined && ($this->declined)())) {
            $this->state->set(SecurityPolicy::SESSION_NEW_DEVICE_PROMPT, true);
        }
    }

    /** @return array<class-string, string> omniauth's events and the method of this class each calls */
    public static function events(): array
    {
        return [SignedIn::class => 'onSignedIn'];
    }
}
