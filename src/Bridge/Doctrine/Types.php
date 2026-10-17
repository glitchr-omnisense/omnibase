<?php

namespace Base\Bridge\Doctrine;

use Base\Database\Type\ArrayType;
use Base\Database\Type\DateTimeTypeUTC;
use Base\Database\Type\EnumType;
use Base\Database\Type\UtcDateTimeImmutableType;
use Base\Database\Type\UtcDateTimeType;
use Base\Service\Model\ClassFinder;
use Doctrine\DBAL\Types\Type;

/**
 * omnibase's column types, registered with DBAL (plan "omnibase agnostique",
 * C6): what BaseBundle::boot() and the bundle's doctrine.dbal.types did, in
 * one place, for Symfony and for an entity manager built by hand.
 *
 * - `datetime` and `datetimetz` are Base\Database\Type\DateTimeTypeUTC (the
 *   moment kept in UTC whatever zone PHP was put in), `array` the core's
 *   ArrayType (the serialized arrays of before DBAL 4), `utc_datetime` and
 *   `utc_datetime_immutable` the core's UTC types;
 * - every enumeration (Base\Database\Type\EnumType, SetType) of the folders
 *   given, under its getStaticName(), when no type has that name yet;
 * - the types given by name (a bundle's enumerations), replacing a type of
 *   the same name, as DoctrineBundle registers its configured types.
 */
final class Types
{
    /** @return array<string, class-string<Type>> the core's types, by name */
    public static function core(): array
    {
        return [
            'datetime' => DateTimeTypeUTC::class,
            'datetimetz' => DateTimeTypeUTC::class,
            'array' => ArrayType::class,
            UtcDateTimeImmutableType::NAME => UtcDateTimeImmutableType::class,
            UtcDateTimeType::NAME => UtcDateTimeType::class,
        ];
    }

    /** @return array<string, class-string<Type>> the enumerations the folders hold, by their names */
    public static function enumerations(array $directories): array
    {
        $types = [];
        foreach ($directories as $directory) {
            foreach (ClassFinder::classes($directory) as $className) {
                // A plain PHP enum (Base\Enum\Allergen, CommentState) is no Doctrine type.
                if (!class_exists($className) || !is_subclass_of($className, Type::class) || !method_exists($className, 'getStaticName')) {
                    continue;
                }

                $types[$className::getStaticName()] ??= $className;
            }
        }

        return $types;
    }

    /**
     * @param array<string, class-string<Type>> $types the types to register by name, over any of that name
     * @param list<string> $enumerationDirectories the folders whose enumerations are registered, under names not taken
     */
    public static function register(array $types = [], array $enumerationDirectories = []): void
    {
        foreach (self::core() + $types as $name => $className) {
            Type::hasType($name) ? Type::overrideType($name, $className) : Type::addType($name, $className);
        }

        foreach (self::enumerations($enumerationDirectories) as $name => $className) {
            if (!Type::hasType($name)) {
                Type::addType($name, $className);
            }
        }
    }

    /** The core's own enumerations (src/Enum). */
    public static function coreEnumerationDirectory(): string
    {
        return dirname((new \ReflectionClass(EnumType::class))->getFileName(), 3).'/Enum';
    }
}
