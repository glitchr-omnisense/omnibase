<?php

namespace Base\Database\Type;

/**
 * An enumeration whose field holds a set of its values (UserRole: an account's roles), kept as
 * those values joined by commas (a MySQL SET). A plain PHP class (plan "omnibase agnostic", step
 * F2a): Omnibridge\Doctrine\Omnibase\EnumTypeAdapter maps it on Doctrine.
 */
abstract class SetType extends EnumType
{
    /** The values joined by commas, those of the enumeration only. */
    public static function toDatabaseValue(mixed $value): mixed
    {
        $values = array_filter($value, fn($v) => in_array($v, static::getPermittedValues()));
        return implode(",", $values);
    }

    /** @return list<string> the values read back, those of the enumeration only */
    public static function toPHPValue(mixed $value): mixed
    {
        $values = $value !== null ? explode(",", $value) : [];
        return array_filter($values, fn($v) => in_array($v, static::getPermittedValues()));
    }

    // @todo: Put this function in @ordercolumn
    public static function getOrderingKeys(array $array): array
    {
        $permittedValues = self::getPermittedValues();

        $ordering = array_filter(
            array_map(fn($a) => ($pos = array_search($a, $permittedValues)) !== false ? $pos : null, $array),
            fn($c) => $c !== null
        );

        asort($ordering);
        return $ordering;
    }
}
