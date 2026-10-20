<?php

namespace Base\Database\Type\Passkey;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

/**
 * The model of a passkey's authenticator (its AAGUID), kept as the RFC 4122
 * text of a UUID (plan "omniauth", AU1). The column is that of
 * web-auth/webauthn-symfony-bundle's `aaguid` type (a CLOB of length 36,
 * TINYTEXT on MySQL), and so is the text (Symfony's Uuid as a string,
 * lowercase); PHP's value is that text, not a Uuid object, so that
 * Base\Entity\User\Passkey loads nothing of webauthn-lib nor symfony/uid. An
 * object that is a UUID (a Stringable, as Symfony's Uuid) is written as its
 * text.
 */
final class Aaguid extends Type
{
    public const NAME = 'passkey_aaguid';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return match (true) {
            null === $value => null,
            \is_string($value) => $value,
            $value instanceof \Stringable => (string) $value,
            default => throw InvalidType::new($value, self::NAME, ['null', 'string', \Stringable::class]),
        };
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return null === $value ? null : (string) $value;
    }
}
