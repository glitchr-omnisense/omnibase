<?php

namespace Base\Form\Definition;

use Base\Database\Repository\Repositories;

/**
 * The lookup of Omnibridge\Validation\Constraint\Unique through the entities' repositories: whether
 * another record than the one checked holds those values. What a host's Validation is given as
 * $exists (omnibridge/symfony's, omnibridge/laravel's).
 */
final class UniqueLookup
{
    public function __construct(private readonly Repositories $repositories)
    {
    }

    /** @param array<string, mixed> $criteria */
    public function __invoke(string $class, array $criteria, ?object $record): bool
    {
        foreach ($this->repositories->of($class)->findBy($criteria, null, 2) as $found) {
            if ($found !== $record) {
                return true;
            }
        }

        return false;
    }
}
