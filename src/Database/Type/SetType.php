<?php

namespace Base\Database\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;

abstract class SetType extends EnumType
{
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        if ($platform instanceof SqlitePlatform) {
            return "TEXT";
        }

        $values = array_map(fn ($val) => "`".$val."`", $this->getPermittedValues());
        return "SET(".implode(", ", $values).")";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        $values = $value !== null ? explode(",", $value) : [];
        return array_filter($values, fn ($v) => in_array($v, $this->getPermittedValues()));
    }

    public function convertToDatabaseValue($values, AbstractPlatform $platform): mixed
    {
        $values = array_filter($values, fn ($v) => in_array($v, $this->getPermittedValues()));
        return implode(",", $values);
    }
}
