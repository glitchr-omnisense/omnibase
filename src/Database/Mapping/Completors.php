<?php

namespace Base\Database\Mapping;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;

/**
 * Each entity class's completor - what omnibase's attributes add to its mapping (#[Alias]'s names, #[Hierarchify]'s
 * hierarchy) - read from the class itself, on no ORM (plan "omnibase agnostic", step E3). It was written by the
 * attributes' loadClassMetadata() into Doctrine's metadata as Doctrine loaded it, then cached by omnibridge/doctrine's
 * ClassMetadataManipulator: a manipulator made again over an empty cache (a second kernel in the same process, in
 * eloquent mode) answered without the aliases until the cache was cleared. Here it is the attributes' complete() hook,
 * run over the class's attributes as omnibase's AttributeReader gives them to the mapping - the class's own, its
 * methods' and its properties' (its own and the inherited ones that are not private) -, once per class and process:
 * the same answer whichever persistence maps the class, and Doctrine's manipulator answers from here too.
 */
final class Completors
{
    /** @var array<string, Completor> */
    private static array $completors = [];

    /** The completor of a class (an object's class), filled by its attributes' complete() hook. */
    public static function of(string|object $class): Completor
    {
        $class = \is_object($class) ? $class::class : ltrim($class, '\\');
        if (isset(self::$completors[$class])) {
            return self::$completors[$class];
        }

        $completor = new Completor($class);
        if (!class_exists($class)) {
            return self::$completors[$class] = $completor;
        }

        $reflection = new \ReflectionClass($class);
        foreach (self::attributes($reflection->getAttributes()) as $attribute) {
            $attribute->complete($completor, AttributeReader::TARGET_CLASS, $class);
        }
        foreach ($reflection->getMethods() as $method) {
            foreach (self::attributes($method->getAttributes()) as $attribute) {
                $attribute->complete($completor, AttributeReader::TARGET_METHOD, $method->getName());
            }
        }
        foreach ($reflection->getProperties() as $property) {
            foreach (self::attributes($property->getAttributes()) as $attribute) {
                $attribute->complete($completor, AttributeReader::TARGET_PROPERTY, $property->getName());
            }
        }

        return self::$completors[$class] = $completor;
    }

    /** What the completor of a class holds, its keys sorted, the values left unread (null) out: a snapshot's. */
    public static function payload(string|object $class): array
    {
        $payload = array_filter(self::of($class)->payload(), static fn ($value) => null !== $value);
        ksort($payload);

        return $payload;
    }

    /**
     * omnibase's attributes among those PHP lists (an attribute whose package is not installed skipped, as the reader
     * does).
     *
     * @param list<\ReflectionAttribute> $declared
     *
     * @return list<AbstractAttribute>
     */
    private static function attributes(array $declared): array
    {
        $attributes = [];
        foreach ($declared as $attribute) {
            $name = $attribute->getName();
            if (!class_exists($name) || !is_subclass_of($name, AbstractAttribute::class)) {
                continue;
            }
            $attributes[] = $attribute->newInstance();
        }

        return $attributes;
    }
}
