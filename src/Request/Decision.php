<?php

namespace Base\Request;

/**
 * What a request rule decided (plan "omnibase agnostic", step F5e, slice 2: Base\Request\Rule\*), for every
 * host to apply: the request goes on, is sent elsewhere (a route of the site's, or an address), or is refused
 * with a status - and, with any of them, notices for the visitor, cookies for the answer, the account signed
 * out, the address to come back to after a sign-in, the profiler turned off. omnibridge/symfony's subscribers
 * and omnibridge/laravel's middleware apply it; neither decides anything itself.
 *
 * `final` is a decision that the page is served as it is and that no rule after this one runs (Symfony: the
 * event's propagation stopped without a response).
 */
final class Decision
{
    public const CONTINUE = 'continue';
    public const REDIRECT = 'redirect';
    public const DENY = 'deny';

    /**
     * @param array<string, mixed>                                                 $parameters the route's
     * @param list<array{0: string, 1: array<int|string, mixed>, 2: string}>       $notices    key (in @notifications unless it names its domain), parameters, importance
     * @param list<array{0: string, 1: ?string}>                                   $cookies    name, value (null: cleared)
     */
    private function __construct(
        public readonly string $kind = self::CONTINUE,
        public readonly ?string $route = null,
        public readonly array $parameters = [],
        public readonly ?string $url = null,
        public readonly int $status = 302,
        public readonly array $notices = [],
        public readonly array $cookies = [],
        public readonly bool $final = false,
        public readonly bool $signOut = false,
        public readonly ?string $remember = null,
        public readonly bool $profiler = true,
    ) {
    }

    public static function continue(): self
    {
        return new self();
    }

    /** The page served as it is, no rule after this one. */
    public static function final(): self
    {
        return new self(final: true);
    }

    /** @param array<string, mixed> $parameters */
    public static function redirect(string $route, array $parameters = [], int $status = 302): self
    {
        return new self(self::REDIRECT, $route, $parameters, status: $status, final: true);
    }

    public static function redirectTo(string $url, int $status = 302): self
    {
        return new self(self::REDIRECT, url: $url, status: $status, final: true);
    }

    public static function deny(int $status = 403): self
    {
        return new self(self::DENY, status: $status, final: true);
    }

    /** @param array<int|string, mixed> $parameters */
    public function withNotice(string $key, array $parameters = [], string $importance = 'info'): self
    {
        return $this->with(['notices' => [...$this->notices, [$key, $parameters, $importance]]]);
    }

    public function withCookie(string $name, ?string $value): self
    {
        return $this->with(['cookies' => [...$this->cookies, [$name, $value]]]);
    }

    public function withSignOut(): self
    {
        return $this->with(['signOut' => true]);
    }

    /** The address the visitor comes back to after signing in again. */
    public function withRemember(string $url): self
    {
        return $this->with(['remember' => $url]);
    }

    public function withoutProfiler(): self
    {
        return $this->with(['profiler' => false]);
    }

    public function isContinue(): bool
    {
        return self::CONTINUE === $this->kind;
    }

    /** This decision's kind, route and status with another's notices and cookies added after its own (rules run in a row). */
    public function then(self $next): self
    {
        return new self(
            $next->kind, $next->route, $next->parameters, $next->url, $next->status,
            [...$this->notices, ...$next->notices], [...$this->cookies, ...$next->cookies],
            $next->final, $this->signOut || $next->signOut, $next->remember ?? $this->remember, $this->profiler && $next->profiler,
        );
    }

    /** A plain array, for a test or a log. */
    public function toArray(): array
    {
        return array_filter([
            'kind' => $this->kind,
            'route' => $this->route,
            'parameters' => $this->parameters ?: null,
            'url' => $this->url,
            'status' => self::CONTINUE === $this->kind ? null : $this->status,
            'notices' => $this->notices ? array_map(static fn (array $n) => [$n[0], array_map(static fn ($p) => \is_object($p) ? ($p instanceof \Stringable ? (string) $p : $p::class) : $p, $n[1]), $n[2]], $this->notices) : null,
            'cookies' => $this->cookies ?: null,
            'final' => $this->final ?: null,
            'signOut' => $this->signOut ?: null,
            'remember' => $this->remember,
            'profiler' => $this->profiler ? null : false,
        ], static fn ($v) => null !== $v);
    }

    /** @param array<string, mixed> $changes */
    private function with(array $changes): self
    {
        $values = get_object_vars($this);

        return new self(...array_replace($values, $changes));
    }
}
