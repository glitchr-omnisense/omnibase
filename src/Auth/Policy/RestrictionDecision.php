<?php

namespace Base\Auth\Policy;

/**
 * Restriction's answer (plan "omnibase agnostic", step F5e, slice 1): the request goes on (a notice for the
 * visitor, perhaps), is answered "not found", or is sent to the first of the routes the denied are sent to
 * (the sign-in when there is none). `profiler` says whether the visitor keeps the profiler (a special grant):
 * the host turns it off otherwise, outside debugging.
 */
final class RestrictionDecision
{
    public const OPEN = 'open';
    public const NOT_FOUND = 'not_found';
    public const REDIRECT = 'redirect';

    /**
     * @param array{0: string, 1: string}|null $notice a notification's key (in @notifications) and its importance
     * @param list<string>                     $routes
     */
    private function __construct(
        public readonly string $kind,
        public readonly bool $profiler = true,
        public readonly ?array $notice = null,
        public readonly array $routes = [],
    ) {
    }

    /** @param array{0: string, 1: string}|null $notice */
    public static function open(bool $profiler = true, ?array $notice = null): self
    {
        return new self(self::OPEN, $profiler, $notice);
    }

    public static function notFound(bool $profiler): self
    {
        return new self(self::NOT_FOUND, $profiler);
    }

    /** @param list<string> $routes */
    public static function redirect(array $routes): self
    {
        return new self(self::REDIRECT, false, null, $routes);
    }
}
