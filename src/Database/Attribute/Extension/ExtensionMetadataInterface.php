<?php

namespace Base\Database\Attribute\Extension;

use Base\Database\Entity\EntityExtensionInterface;

interface ExtensionMetadataInterface extends EntityExtensionInterface
{
    public function payload(string $action, string $className, array $properties, object $entity): array;

    public static function get(): array;

    public static function has(string $className, ?string $property = null): bool;
}