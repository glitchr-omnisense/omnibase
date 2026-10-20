<?php

namespace Base\Security;

use Base\Service\FormGuard;
use Omnishield\Exception\InvalidKeyException;
use Omnishield\Exception\ProviderException;
use Omnishield\Model\Attempt;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * The sign-in's captcha, after a few failures. Symfony's login_throttling is
 * the first line (five tries a minute, per identifier and address); this one
 * counts the failures from an address - whatever identifiers they tried -
 * and from base.guard.sign_in_after of them on, the sign-in form carries the
 * captcha (SecurityController::Login) and a sign-in without a valid token is
 * refused before its password is even checked. A success forgets the count.
 *
 * Only the site's own sign-in form (security_login): not the rescue door, not
 * the demonstration's one click. Nothing where glitchr/ux-google's reCAPTCHA
 * already guards the sign-in (google.recaptcha.enable), nor without
 * glitchr/omnishield and a captcha.
 */
class SignInGuard implements EventSubscriberInterface
{
    public const ACTION = 'login';
    public const MESSAGE = 'Please confirm that you are not a robot.';
    private const WINDOW = 900; // seconds a failure is remembered

    public function __construct(
        protected readonly FormGuard $guard,
        protected readonly ?CacheItemPoolInterface $cache = null,
        protected readonly bool $google = false,
        protected readonly ?LoggerInterface $logger = null,
        protected readonly ?RequestStack $requests = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After login_throttling (2080) and before the password is checked (0).
            CheckPassportEvent::class => ['onCheckPassport', 300],
            LoginFailureEvent::class => ['onLoginFailure'],
            LoginSuccessEvent::class => ['onLoginSuccess'],
        ];
    }

    /** The captcha's gateway when a sign-in from this request must pass it; null otherwise. */
    public function required(?Request $request): ?string
    {
        return $this->requiredFor($request?->getClientIp());
    }

    /**
     * The captcha's gateway when a sign-in from this address must pass it;
     * null otherwise. What required() asks of a request, asked of its address
     * alone: omniauth's listener (Base\Auth\Listener\GuardSignIn) has no
     * request, only omniauth's Context.
     */
    public function requiredFor(?string $ip): ?string
    {
        $after = $this->guard->signInAfter();
        if ($this->google || $after <= 0 || null === $ip || '' === $ip) {
            return null;
        }
        $gateway = $this->guard->challengeGateway();

        return null !== $gateway && $this->failures($ip) >= $after ? $gateway : null;
    }

    public function failures(string $ip): int
    {
        if (null === $this->cache) {
            return 0;
        }
        $item = $this->cache->getItem($this->key($ip));

        return $item->isHit() ? (int) $item->get() : 0;
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if (self::byOmniauth($event->getPassport()) || $event->getException() instanceof \Omnibridge\Symfony\Auth\Exception\SignInRefused) {
            return; // Base\Auth\Listener\GuardSignIn counted it, on omniauth's SignInFailed
        }
        $ip = $event->getRequest()->getClientIp();
        if (null !== $ip) {
            $this->failed($ip);
        }
    }

    /** One more failure from this address, remembered WINDOW seconds. */
    public function failed(string $ip): void
    {
        if (null === $this->cache || '' === $ip) {
            return;
        }
        $item = $this->cache->getItem($this->key($ip));
        $this->cache->save($item->set(($item->isHit() ? (int) $item->get() : 0) + 1)->expiresAfter(self::WINDOW));
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if (self::byOmniauth($event->getPassport())) {
            return;
        }
        $ip = $event->getRequest()->getClientIp();
        if (null !== $ip) {
            $this->forget($ip);
        }
    }

    /** The failures counted from an address forgotten: a success does it; a test that starts afresh too. */
    public function forget(string $ip): void
    {
        $this->cache?->deleteItem($this->key($ip));
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        if (self::byOmniauth($event->getPassport())) {
            return; // the captcha was asked before omniauth checked the password (GuardSignIn, on Verifying)
        }
        $request = $this->requests?->getCurrentRequest();
        if (null === $request || LoginFormAuthenticator::LOGIN_ROUTE !== $request->attributes->get('_route') || !$request->isMethod('POST') || $event->getAuthenticator() instanceof RescueFormAuthenticator) {
            return;
        }
        if (null === $gateway = $this->required($request)) {
            return;
        }

        $field = $this->field($gateway);
        $form = $request->request->all('_base_security_login') ?: $request->request->all('security_login') ?: [];
        $token = $request->request->get($field) ?? ($form[FormGuard::CHALLENGE_FIELD] ?? '');

        if (!$this->passes($gateway, \is_string($token) ? $token : '', $request->getClientIp())) {
            throw new CustomUserMessageAuthenticationException(self::MESSAGE);
        }
    }

    /**
     * A sign-in omniauth decided (omnibridge/symfony's OmniauthAuthenticator,
     * plan "omniauth" AU7): its own events were heard, Base\Auth\Listener\GuardSignIn
     * guarded it.
     */
    public static function byOmniauth(?\Symfony\Component\Security\Http\Authenticator\Passport\Passport $passport): bool
    {
        return null !== $passport && $passport->hasBadge(\Omnibridge\Symfony\Auth\OmniauthBadge::class);
    }

    /** The form field the captcha's widget posts its answer in. */
    public function field(string $gateway): string
    {
        return $this->guard->getRegistry()->challenge($gateway)->widget(self::ACTION)->field;
    }

    /**
     * Does the captcha's answer pass? A key the captcha refuses lets the
     * sign-in through (the site's mistake, logged); a captcha that does not
     * answer, unless the guard rejects the unreachable (base.guard).
     */
    public function passes(string $gateway, string $token, ?string $ip): bool
    {
        try {
            return $this->guard->getRegistry()->challenge($gateway)->verify(new Attempt($token, $ip, self::ACTION))->passed;
        } catch (InvalidKeyException $e) {
            $this->logger?->error('Sign-in guard: the captcha refused the site\'s key: {message}', ['message' => $e->getMessage()]);

            return true;
        } catch (ProviderException $e) {
            $this->logger?->warning('Sign-in guard: the captcha did not answer: {message}', ['message' => $e->getMessage()]);

            return !$this->guard->rejectsUnreachable();
        }
    }

    private function key(string $ip): string
    {
        return 'base.sign_in_failures.'.hash('sha256', $ip);
    }
}
