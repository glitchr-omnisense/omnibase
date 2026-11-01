<?php

namespace Base\Bridge\Doctrine;

use Base\Database\Scope\TrashScope;
use Base\Database\Scope\VaultScope;
use Omnibridge\Persistence\Backend;
use Omnibridge\Persistence\UnitOfWork;

/**
 * omnisense/omnibridge's Backend on Doctrine (plan "omnibase agnostic", step F2e): its unit of work, what
 * it can do, and the core's scopes as its SQL filters - "trash" is the filter "trash_filter"
 * (Base\Database\Filter\TrashFilter, which compiles Base\Database\Scope\TrashScope), "vault" is
 * "vault_filter" (VaultFilter, VaultScope, enabled with the environment). Registered for DoctrineUnitOfWork
 * in Backends, and the Symfony service Omnibridge\Persistence\Backend. Moves to omnibridge/doctrine at F3.
 */
final class DoctrineBackend implements Backend
{
    /** The core's scopes by name, and Doctrine's filter compiling each. */
    public const FILTERS = [
        TrashScope::NAME => Configuration::TRASH_FILTER,
        VaultScope::NAME => Configuration::VAULT_FILTER,
    ];

    public function __construct(private readonly DoctrineUnitOfWork $unitOfWork)
    {
    }

    public function name(): string
    {
        return 'doctrine';
    }

    public function supports(string $feature): bool
    {
        return match ($feature) {
            self::SECOND_LEVEL_CACHE => null !== $this->unitOfWork->entityManager()->getCache(),
            self::RESULT_CACHE => null !== $this->unitOfWork->entityManager()->getConfiguration()->getResultCache(),
            self::LAZY_ASSOCIATIONS, self::TRANSACTIONS => true,
            default => false,
        };
    }

    public function unitOfWork(): UnitOfWork
    {
        return $this->unitOfWork;
    }

    public function enableScope(string $name, array $parameters = []): void
    {
        $filter = $this->unitOfWork->entityManager()->getFilters()->enable(self::filter($name));
        if (VaultScope::NAME === $name && isset($parameters['environment'])) {
            $filter->setEnvironment($parameters['environment']);
        }
    }

    public function disableScope(string $name): void
    {
        $filters = $this->unitOfWork->entityManager()->getFilters();
        if ($filters->isEnabled(self::filter($name))) {
            $filters->disable(self::filter($name));
        }
    }

    public function isScopeEnabled(string $name): bool
    {
        return $this->unitOfWork->entityManager()->getFilters()->isEnabled(self::filter($name));
    }

    private static function filter(string $name): string
    {
        return self::FILTERS[$name] ?? throw new \InvalidArgumentException(sprintf('No scope "%s" on Doctrine (%s).', $name, implode(', ', array_keys(self::FILTERS))));
    }
}
