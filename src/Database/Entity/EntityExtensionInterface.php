<?php

namespace Base\Database\Entity;

/**
 *
 */
interface EntityExtensionInterface
{
    public function payload(string $action, string $className, array $properties, object $entity): array;

    public static function get(): array;

    public static function has(string $className, ?string $property = null): bool;
}
