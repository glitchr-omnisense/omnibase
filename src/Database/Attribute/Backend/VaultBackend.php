<?php

namespace Base\Database\Attribute\Backend;

/**
 * #[Vault]'s part of the persistence (plan "omnibase agnostic", step F2b): an
 * entity whose secured field was given a new plain value is written again,
 * though the persistence sees no change in it (the field still holds the value
 * it read, the new one is sealed at the update). Doctrine: the unit of work's
 * scheduleForUpdate().
 */
interface VaultBackend
{
    /** The entity updated at the running flush, whatever its change set says. */
    public function scheduleUpdate(object $entity): void;
}
