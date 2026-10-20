<?php

namespace Base\Auth\Listener;

use Base\Security\SignInGuard;
use Omniauth\Event\SignedIn;
use Omniauth\Event\SignInFailed;
use Omniauth\Event\Verifying;
use Omniauth\Factor;

/**
 * Base\Security\SignInGuard on omniauth's events: each failed sign-in from an
 * address counts (SignInFailed), a success forgets them (SignedIn), and from
 * base.guard.sign_in_after failures on, a password sign-in from that address
 * must pass the captcha before its password is checked (Verifying, refused
 * `captcha`: SignInGuard::MESSAGE is what the page says).
 *
 * The captcha's answer is a field of the host's form, which omniauth's events
 * do not carry (no proof is handed to a listener): $answer gives it - the
 * host reads its request (the field SignInGuard::field() names). As today,
 * only the site's own sign-in ($firewalls: not the rescue door).
 */
class GuardSignIn
{
    public const REFUSED = 'captcha';

    /**
     * @param \Closure(string $field): ?string $answer   the captcha's answer the visitor posted in that field
     * @param list<string>                     $firewalls the sign-ins guarded (Context::$firewall)
     */
    public function __construct(
        protected readonly SignInGuard $guard,
        protected readonly ?\Closure $answer = null,
        protected readonly array $firewalls = ['main'],
    ) {
    }

    public function onVerifying(Verifying $event): void
    {
        if (Factor::Password !== $event->factor || !\in_array($event->context->firewall, $this->firewalls, true)) {
            return;
        }
        $gateway = $this->guard->requiredFor($event->context->ip);
        if (null === $gateway) {
            return;
        }
        $answer = null !== $this->answer ? ($this->answer)($this->guard->field($gateway)) : null;
        if (!$this->guard->passes($gateway, \is_string($answer) ? $answer : '', $event->context->ip)) {
            $event->refuse(self::REFUSED);
        }
    }

    public function onSignInFailed(SignInFailed $event): void
    {
        if (null !== $event->context->ip && \in_array($event->context->firewall, $this->firewalls, true)) {
            $this->guard->failed($event->context->ip);
        }
    }

    public function onSignedIn(SignedIn $event): void
    {
        if (null !== $event->context->ip) {
            $this->guard->forget($event->context->ip);
        }
    }

    /** @return array<class-string, string> omniauth's events and the method of this class each calls */
    public static function events(): array
    {
        return [Verifying::class => 'onVerifying', SignInFailed::class => 'onSignInFailed', SignedIn::class => 'onSignedIn'];
    }
}
