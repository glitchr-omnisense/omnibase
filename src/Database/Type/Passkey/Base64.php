<?php

namespace Base\Database\Type\Passkey;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Bytes kept as standard base64 text (plan "omniauth", AU1): a passkey's
 * credential id and its public key (Base\Entity\User\Passkey). The column and
 * the text are those of web-auth/webauthn-symfony-bundle's `base64` type,
 * which wrote the table first (a CLOB, LONGTEXT on MySQL; base64 with its
 * padding, ParagonIE's constant-time encoding giving PHP's own), so a row
 * written by either is read by the other - without the bundle, and under its
 * own name, so that a site keeping the bundle keeps the bundle's type too.
 * PHP's value is the bytes, a string.
 */
final class Base64 extends Type
{
    public const NAME = 'passkey_base64';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!\is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'string']);
        }

        return base64_encode($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        $bytes = \is_string($value) ? base64_decode($value, true) : false;
        if (false === $bytes) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        return $bytes;
    }
}
