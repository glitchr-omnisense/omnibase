<?php

namespace Base\Auth\Policy;

/**
 * The page a request opens, as the restriction asks it (plan "omnibase agnostic", step F5e, slice 1):
 * what only the host knows - its router, its firewalls -, asked lazily, only once the access is restricted.
 */
interface Page
{
    /** A page of the sign-in's own (the rescue door must stay open). */
    public function isSecurity(): bool;

    /** The profiler's or the debugging toolbar's. */
    public function isProfiler(): bool;

    /** The back office's. */
    public function isAdmin(): bool;

    /** The route's name, null for none. */
    public function name(): ?string;

    /** @return list<string> the routes a denied visitor is sent to (`base.settings.access_restriction.redirect_on_deny`), their default language's suffix taken off */
    public function redirectsOnDeny(): array;
}
