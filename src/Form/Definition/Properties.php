<?php

namespace Base\Form\Definition;

/**
 * Reads and writes a property of the data a form edits, the way Symfony's
 * PropertyAccess does for a form's paths - getX()/isX()/hasX() and setX(), a
 * public property, an array's key, "." paths - without its property-info and
 * type-info, which the core does not keep (bare --strict).
 */
final class Properties
{
    private function __construct()
    {
    }

    public static function isReadable(mixed $data, string $path): bool
    {
        try {
            self::get($data, $path);

            return true;
        } catch (\UnexpectedValueException) {
            return false;
        }
    }

    /** The value at the path; throws \UnexpectedValueException when the data has no such property. */
    public static function get(mixed $data, string $path): mixed
    {
        foreach (explode('.', $path) as $property) {
            $data = self::read($data, $property);
        }

        return $data;
    }

    /** Writes the value at the path (the data given by reference: an array is completed). */
    public static function set(mixed &$data, string $path, mixed $value): void
    {
        $properties = explode('.', $path);
        $last = array_pop($properties);
        if ([] === $properties) {
            self::write($data, $last, $value);

            return;
        }

        $first = array_shift($properties);
        $inner = self::read($data, $first);
        self::set($inner, implode('.', [...$properties, $last]), $value);
        if (\is_array($data)) {
            $data[$first] = $inner;
        }
    }

    private static function read(mixed $data, string $property): mixed
    {
        if (\is_array($data) || $data instanceof \ArrayAccess) {
            return $data[$property] ?? null;
        }
        if (!\is_object($data)) {
            throw new \UnexpectedValueException(sprintf('No property "%s" on a value that is not an object.', $property));
        }

        $camel = self::camelize($property);
        foreach (['get', 'is', 'has', 'can'] as $prefix) {
            if (method_exists($data, $prefix.$camel) && \is_callable([$data, $prefix.$camel])) {
                return $data->{$prefix.$camel}();
            }
        }
        if (property_exists($data, $property) && (new \ReflectionProperty($data, $property))->isPublic()) {
            return $data->{$property};
        }
        if (method_exists($data, '__get') && (!method_exists($data, '__isset') || isset($data->{$property}))) {
            return $data->{$property};
        }

        throw new \UnexpectedValueException(sprintf('The class "%s" has no readable property "%s".', $data::class, $property));
    }

    private static function write(mixed &$data, string $property, mixed $value): void
    {
        if (\is_array($data) || null === $data) {
            $data ??= [];
            $data[$property] = $value;

            return;
        }
        if ($data instanceof \ArrayAccess) {
            $data[$property] = $value;

            return;
        }
        if (!\is_object($data)) {
            throw new \UnexpectedValueException(sprintf('No property "%s" on a value that is not an object.', $property));
        }

        $setter = 'set'.self::camelize($property);
        if (method_exists($data, $setter) && \is_callable([$data, $setter])) {
            $data->{$setter}($value);

            return;
        }
        if (property_exists($data, $property) && (new \ReflectionProperty($data, $property))->isPublic()) {
            $data->{$property} = $value;

            return;
        }
        if (method_exists($data, '__set')) {
            $data->{$property} = $value;

            return;
        }

        throw new \UnexpectedValueException(sprintf('The class "%s" has no writable property "%s".', $data::class, $property));
    }

    private static function camelize(string $property): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $property)));
    }
}
