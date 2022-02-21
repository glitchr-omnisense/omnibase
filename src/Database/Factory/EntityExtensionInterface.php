<?php

namespace Base\Database\Factory;

interface EntityExtensionInterface
{
    public function payload(string $action, string $className, array $properties, object $entity): array;
    
    public static function get(): array;
    public static function has($entity, $property):bool;
}