<?php

namespace Base\Security;

use Base\Service\FormGuard;
use Omnibridge\Contract\Translations;
use Omnishield\Exception\InvalidKeyException;
use Omnishield\Exception\ProviderException;
use Omnishield\LocalizableInterface;
use Omnishield\Model\Attempt;
use Omnishield\WidgetPrinter;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * The sign-in's captcha after a few failures, framework-free (plan
 * "omniauth", step AU8b): the failures from an address are counted - whatever
 * identifiers they tried - and from base.guard.sign_in_after of them on, a
 * sign-in from that address must pass the captcha; a success forgets the
 * count. Counted in a PSR-6 pool: one both hosts reach (lesringostars' Redis)
 * makes a captcha one host asks for asked by the other as well.
 *
 * Base\Security\SignInGuard is it under Symfony (its security events, the
 * sign-in page's form); Base\Auth\Listener\GuardSignIn is it on omniauth's
 * events, under any host. Nothing where glitchr/ux-google's reCAPTCHA
 * already guards the sign-in ($google), nor without glitchr/omnishield and a
 * captcha.
 */
class SignInCaptcha
{
    public const ACTION = 'login';
    public const MESSAGE = 'Please confirm that you are not a robot.';
    protected const WINDOW = 900; // seconds a failure is remembered

    public function __construct(
        protected readonly FormGuard $guard,
        protected readonly ?CacheItemPoolInterface $cache = null,
        protected readonly bool $google = false,
        protected readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * The captcha's gateway when a sign-in from this address must pass it;
     * null otherwise.
     */
    public function requiredFor(?string $ip): ?string
    {
        $after = $this->guard->signInAfter();
        if ($this->google || $after <= 0 || null === $ip || '' === $ip) {
            return null;
        }
        $gateway = $this->gateway();

        return null !== $gateway && $this->failures($ip) >= $after ? $gateway : null;
    }

    /** The captcha's gateway, its widget printed by the host (FormGuard::challengeGateway() without omnishield's form field). */
    protected function gateway(): ?string
    {
        return $this->guard->challengeGateway(false);
    }

    public function failures(string $ip): int
    {
        if (null === $this->cache) {
            return 0;
        }
        $item = $this->cache->getItem($this->key($ip));

        return $item->isHit() ? (int) $item->get() : 0;
    }

    /** One more failure from this address, remembered WINDOW seconds. */
    public function failed(string $ip): void
    {
        if (null === $this->cache || '' === $ip) {
            return;
        }
        $item = $this->cache->getItem($this->key($ip));
        $this->cache->save($item->set(($item->isHit() ? (int) $item->get() : 0) + 1)->expiresAfter(static::WINDOW));
    }

    /** The failures counted from an address forgotten: a success does it; a test that starts afresh too. */
    public function forget(string $ip): void
    {
        $this->cache?->deleteItem($this->key($ip));
    }

    /** The form field the captcha's widget posts its answer in. */
    public function field(string $gateway): string
    {
        return $this->guard->getRegistry()->challenge($gateway)->widget(self::ACTION)->field;
    }

    /**
     * The captcha's widget for a sign-in page from this address, as the
     * sign-in form prints it under Symfony (omnishield's ChallengeType, its
     * WidgetLocalizer): the gateway's widget for the action "login", in the
     * page's language - its texts from the "omnishield" catalogue -, printed
     * by omnishield's WidgetPrinter. '' when no captcha is asked. For a host
     * whose sign-in page is not a Symfony form (omnibridge/laravel's).
     *
     * The widget's language is the locale's language ("fr" for fr_FR), as
     * WidgetLocalizer gives it when the catalogue of the full locale defines
     * none of the texts - omnishield ships language catalogues alone.
     */
    public function widgetHtml(?string $ip, ?Translations $translations = null, ?string $locale = null, ?string $nonce = null): string
    {
        $gateway = $this->requiredFor($ip);
        $challenge = null !== $gateway ? $this->guard->getRegistry()?->challenge($gateway) : null;
        if (null === $challenge) {
            return '';
        }
        if ($challenge instanceof LocalizableInterface && null !== $translations && null !== $locale && '' !== $locale) {
            $strings = [];
            foreach ($challenge->texts() as $text) {
                $id = $challenge->getName().'.'.$text;
                $translated = $translations->trans($id, [], 'omnishield', $locale);
                if ($translated !== $id && '' !== $translated) {
                    $strings[$text] = $translated;
                }
            }
            $language = $challenge->language() ?? (string) preg_split('/[-_]/', $locale, 2)[0];
            $challenge = $challenge->localized('' !== $language ? $language : $locale, $strings);
        }

        return (new WidgetPrinter())->print($challenge->widget(self::ACTION), $nonce);
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

    protected function key(string $ip): string
    {
        return 'base.sign_in_failures.'.hash('sha256', $ip);
    }
}
