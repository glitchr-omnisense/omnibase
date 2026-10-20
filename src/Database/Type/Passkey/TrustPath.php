<?php

namespace Base\Database\Type\Passkey;

use Doctrine\DBAL\Types\JsonType;

/**
 * A passkey's attestation trust path, as the JSON it is kept in (plan
 * "omniauth", AU1): `[]` for none (EmptyTrustPath), `{"x5c": [...]}` for a
 * chain of certificates. The column and the text are those of
 * web-auth/webauthn-symfony-bundle's `trust_path` type (a JSON column, what
 * webauthn-lib's normalizer gives, encoded by json_encode() as Doctrine's
 * JSON type encodes it); PHP's value is the decoded array, not a TrustPath
 * object - webauthn-lib's TrustPathDenormalizer makes one of it.
 */
final class TrustPath extends JsonType
{
    public const NAME = 'passkey_trust_path';
}
