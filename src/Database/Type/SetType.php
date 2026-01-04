<?php

namespace Base\Database\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;

/**
 *
 */
abstract class SetType extends EnumType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if ($platform instanceof SqlitePlatform) {
            return "TEXT";
        }

        $values = array_map(fn($val) => "'" . $val . "'", $this->getPermittedValues());
        return "SET(" . implode(", ", $values) . ")";
    }

    /**
     * @param $value
     * @param AbstractPlatform $platform
     * @return mixed
     * @throws \Exception
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        $values = $value !== null ? explode(",", $value) : [];
        return array_filter($values, fn($v) => in_array($v, $this->getPermittedValues()));
    }

    /**
     * @param $value
     * @param AbstractPlatform $platform
     * @return mixed
     * @throws \Exception
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        $values = array_filter($value, fn($v) => in_array($v, $this->getPermittedValues()));
        return implode(",", $values);
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
