<?php

namespace Base\Bridge\Doctrine;

use Base\Database\Type\EnumType;
use Base\Database\Type\SetType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Type;

/**
 * An enumeration of omnibase (Base\Database\Type\EnumType, SetType) as Doctrine's column type:
 * one instance per enumeration, registered under its name (Types; plan "omnibase agnostic", step
 * F2a). An ENUM('A', 'B') column - SET('A', 'B') for a SetType -, a CLOB on SQLite; the values
 * converted by the enumeration's own toDatabaseValue() and toPHPValue(). The enumeration itself is a
 * plain PHP class, which another persistence maps the same way.
 */
class EnumTypeAdapter extends Type
{
    /** @param class-string<EnumType> $enumeration */
    public function __construct(private readonly string $enumeration)
    {
        if (!is_subclass_of($enumeration, EnumType::class)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is no enumeration of omnibase (%s).', $enumeration, EnumType::class));
        }
    }

    /** @return class-string<EnumType> */
    public function enumeration(): string
    {
        return $this->enumeration;
    }

    public function isSet(): bool
    {
        return is_subclass_of($this->enumeration, SetType::class);
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        // SQLite has neither: a CLOB, which is what SQLite's TEXT is read back as (a TEXT column was
        // found changed by every comparison).
        if ($platform instanceof SQLitePlatform) {
            return $platform->getClobTypeDeclarationSQL($column);
        }

        return self::declaration($this->enumeration::getPermittedValues(), $platform, $this->isSet() ? 'SET' : 'ENUM');
    }

    /**
     * ENUM('A', 'B') or SET('A', 'B'): written as DBAL writes an ENUM column's declaration, so that
     * the column read back from the database (DBAL's EnumType, or MySQLSetType for a SET) compares
     * equal while its values are the enumeration's.
     *
     * @param list<string> $values
     */
    public static function declaration(array $values, AbstractPlatform $platform, string $keyword = 'SET'): string
    {
        return $keyword . "(" . implode(", ", array_map($platform->quoteStringLiteral(...), array_values($values))) . ")";
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return $this->enumeration::toPHPValue($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return $this->enumeration::toDatabaseValue($value);
    }
}
