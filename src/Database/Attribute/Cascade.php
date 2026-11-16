<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;
use Base\Database\Entity\EntityExtension;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
class Cascade extends AbstractAttribute implements ExtensionOptionInterface
{
    /**
     * @param string       $column the association a class-level #[Cascade] names
     * @param list<string> $value  its cascade (persist, remove...)
     *
     * Both are read from the attribute's arguments by resolve(), the attribute never instantiated for them.
     */
    public function __construct(string $column = "", array $value = [])
    {
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == EntityExtension::TARGET_CLASS || $target == EntityExtension::TARGET_PROPERTY);
    }

    /**
     * What #[Cascade] and #[OrphanRemoval] make of an association: its cascade and its orphan removal, given what its
     * mapping declares - the attributes on the property, on the properties that are its #[Alias], or on the class
     * naming it (`column`), the parents' first and the class's own last. ONE implementation, read by reflection: Doctrine's
     * mapping (omnibridge/doctrine relays it when a class's metadata loads) and Eloquent's cascades take it.
     *
     * @internal
     *
     * @param list<string> $cascade
     *
     * @return array{0: list<string>, 1: bool}
     */
    public static function resolve(string $class, string $property, array $cascade, bool $orphanRemoval): array
    {
        $classes = [];
        for ($current = $class; false !== $current && class_exists($current); $current = get_parent_class($current)) {
            array_unshift($classes, $current);
        }
        foreach ($classes as $current) {
            $reflection = new \ReflectionClass($current);
            $aliases = self::aliasesOf($reflection, $property);
            $attributes = [];
            foreach ([$property, ...$aliases] as $name) {
                if ($reflection->hasProperty($name) && $reflection->getProperty($name)->getDeclaringClass()->getName() === $current) {
                    foreach ($reflection->getProperty($name)->getAttributes() as $attribute) {
                        $attributes[] = [$attribute, true];
                    }
                }
            }
            foreach ($reflection->getAttributes() as $attribute) {
                $attributes[] = [$attribute, false];
            }
            foreach ($attributes as [$attribute, $onProperty]) {
                if (!\in_array($attribute->getName(), [self::class, OrphanRemoval::class], true)) {
                    continue;
                }
                $arguments = $attribute->getArguments();
                $column = $arguments['column'] ?? $arguments[0] ?? '';
                if (!$onProperty && $column !== $property && !\in_array($column, $aliases, true)) {
                    continue;
                }
                $value = $arguments['value'] ?? $arguments[1] ?? null;
                if (self::class === $attribute->getName()) {
                    $cascade = array_values(array_map('strtolower', (array) ($value ?? [])));
                } else {
                    $orphanRemoval = null === $value ? true : (bool) $value;
                }
            }
        }

        return [$cascade, $orphanRemoval];
    }

    /**
     * The properties a class declares as another's #[Alias] (its `column`).
     *
     * @param \ReflectionClass<object> $reflection
     *
     * @return list<string>
     */
    private static function aliasesOf(\ReflectionClass $reflection, string $property): array
    {
        $aliases = [];
        foreach ($reflection->getProperties() as $candidate) {
            if ($candidate->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            foreach ($candidate->getAttributes(Alias::class) as $alias) {
                $arguments = $alias->getArguments();
                if (($arguments['column'] ?? $arguments[0] ?? null) === $property) {
                    $aliases[] = $candidate->getName();
                }
            }
        }

        return $aliases;
    }
}
