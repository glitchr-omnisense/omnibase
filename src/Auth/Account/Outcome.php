<?php

namespace Base\Auth\Account;

use Base\Screen\Template;

/**
 * Where an account's page sends the visitor once its rule has decided (plan "omnibase agnostic", step F5e,
 * slice 5): one of omnibase's routes, an address, the site's index - its route, or its address -, the page
 * the visitor was going to (else the index's address), a page to show (a Template), or a refusal (403) or no
 * such page (404); first signing an account in, remembered or not, where the rule says so. The rule decides
 * and sends its notifications; each host turns the Outcome into its answer (omnibridge/symfony's
 * Omnibase\Response\Outcomes, omnibridge/laravel's Http\Outcomes): which router, which referrer, which
 * authenticator are the host's.
 */
final readonly class Outcome
{
    public const ROUTE = 'route';
    public const URL = 'url';
    /** The index's route (base.site.index), redirected to by its name. */
    public const INDEX = 'index';
    /** The index's address. */
    public const HOME = 'home';
    /** The page the visitor was going to, else the index's address. */
    public const BACK = 'back';
    public const PAGE = 'page';
    /** Nothing more than the sign-in: the host answers what signing in answers (its own page, else the index). */
    public const SIGNED_IN = 'signed-in';
    public const DENIED = 'denied';
    public const MISSING = 'missing';

    /** @param array<string, mixed> $parameters */
    private function __construct(
        public string $kind,
        public ?string $route = null,
        public array $parameters = [],
        public ?string $url = null,
        public ?Template $page = null,
        public ?object $signIn = null,
        public bool $remember = false,
        /** Why a refusal or a missing page answers so (the host's message). */
        public ?string $reason = null,
    ) {
    }

    /** @param array<string, mixed> $parameters */
    public static function route(string $route, array $parameters = []): self
    {
        return new self(self::ROUTE, $route, $parameters);
    }

    public static function url(string $url): self
    {
        return new self(self::URL, url: $url);
    }

    public static function index(): self
    {
        return new self(self::INDEX);
    }

    public static function home(): self
    {
        return new self(self::HOME);
    }

    public static function back(): self
    {
        return new self(self::BACK);
    }

    public static function page(Template $page): self
    {
        return new self(self::PAGE, page: $page);
    }

    public static function signedIn(object $user, bool $remember = false): self
    {
        return new self(self::SIGNED_IN, signIn: $user, remember: $remember);
    }

    public static function denied(?string $reason = null): self
    {
        return new self(self::DENIED, reason: $reason);
    }

    public static function missing(?string $reason = null): self
    {
        return new self(self::MISSING, reason: $reason);
    }

    /** The same, once this account is signed in (remembered: the remember-me cookie as well). */
    public function signingIn(object $user, bool $remember = false): self
    {
        return new self($this->kind, $this->route, $this->parameters, $this->url, $this->page, $user, $remember, $this->reason);
    }

    /** @return array<string, mixed> what a test compares (the account signed in by its string) */
    public function toArray(): array
    {
        return array_filter([
            'kind' => $this->kind,
            'route' => $this->route,
            'parameters' => $this->parameters ?: null,
            'url' => $this->url,
            'page' => null !== $this->page ? [$this->page->template, array_keys($this->page->data)] : null,
            'signIn' => null !== $this->signIn ? (string) (method_exists($this->signIn, '__toString') ? $this->signIn : $this->signIn::class) : null,
            'remember' => $this->remember ?: null,
            'reason' => $this->reason,
        ], static fn ($value) => null !== $value);
    }
}
