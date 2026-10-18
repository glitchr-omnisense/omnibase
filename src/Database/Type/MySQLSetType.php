<?php

namespace Base\Database\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Type;

/**
 * A SET column as the database has it, its values in the column's `values`
 * option, as DBAL's own EnumType is to an ENUM column: what
 * Base\Bridge\Doctrine\MySQLSchemaManager reads a SET column as, so that it
 * compares with the declaration of the Base\Database\Type\SetType it maps
 * (SET('A', 'B'), the same values, the same order) and differs from it once
 * the enumeration's values change. A mapping names its SetType, never this.
 */
final class MySQLSetType extends Type
{
    public const NAME = 'omnibase_mysql_set';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if ($platform instanceof SQLitePlatform) {
            return $platform->getClobTypeDeclarationSQL($column);
        }

        return SetType::declaration($column['values'] ?? [], $platform);
    }

    /** @return list<string> */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        return $value === null || $value === '' ? [] : explode(',', (string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value === null ? null : implode(',', (array) $value);
    }
}
