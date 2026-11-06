<?php

namespace Base\Request\Rule;

use Base\Request\Decision;

/**
 * The pages an application keeps for some accounts, by their path (plan "omnibase agnostic", step F5e,
 * slice 2): the `access_control` of Symfony's security configuration as data - its first entry whose path
 * matches decides: PUBLIC_ACCESS opens the page; else one of its roles or attributes granted opens it; else
 * the visitor meets the entry point (EntryPoint: the sign-in, or 403 once signed in). No entry matching, the
 * page is open. Symfony's firewall applies its own configuration; a host without one (omnibridge/laravel)
 * asks this rule with the same entries.
 */
final class AccessControl
{
    public const PUBLIC_ACCESS = 'PUBLIC_ACCESS';

    /** @param list<array{path: string, roles: string|list<string>}> $entries as security.yaml writes them (`^/profile`, ROLE_USER) */
    public function __construct(private readonly array $entries)
    {
    }

    /** @param \Closure(string): bool $granted the host's decision on a role or an attribute, for the visitor */
    public function decide(string $path, bool $signedIn, \Closure $granted, ?string $uri = null): Decision
    {
        foreach ($this->entries as $entry) {
            if (!isset($entry['path']) || 1 !== preg_match('{'.$entry['path'].'}', rawurldecode($path))) {
                continue;
            }
            $roles = (array) ($entry['roles'] ?? []);
            if ([] === $roles || \in_array(self::PUBLIC_ACCESS, $roles, true)) {
                return Decision::continue();
            }
            foreach ($roles as $role) {
                if ($granted($role)) {
                    return Decision::continue();
                }
            }

            return EntryPoint::decide($signedIn, $uri, $granted('EXCEPTION_ACCESS'));
        }

        return Decision::continue();
    }
}
