<?php

namespace Base\Database\Scope;

use Base\Database\Attribute\Trasheable;
use Omnibridge\Persistence\Scope;

/**
 * The trashed rows left out of every read (plan "omnibase agnostic", step
 * F2e): an entity whose class, or a parent's, is #[Trasheable] is read only
 * while its deletion field is null. Base\Service\TrashManager::withTrash()
 * disables it to look at the trash. Doctrine compiles it as its SQL filter
 * "trash_filter" (Omnibridge\Doctrine\Omnibase\Database\Filter\TrashFilter).
 */
final class TrashScope implements Scope
{
    public const NAME = 'trash';

    public function name(): string
    {
        return self::NAME;
    }

    public function appliesTo(string $class): bool
    {
        return null !== Trasheable::resolve($class);
    }

    /** @return array<string, null> the deletion field => null (IS NULL) */
    public function criteria(string $class, array $parameters = []): array
    {
        $trasheable = Trasheable::resolve($class);

        return null === $trasheable ? [] : [$trasheable->deletedAt => null];
    }
}
