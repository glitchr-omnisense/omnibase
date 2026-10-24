<?php

namespace Base\Repository\User;

use Base\Database\Repository\Entities;
use Base\Entity\User\Token;

/**
 * The accounts' tokens (sign-in links, address checks, password resets, API keys) (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): TokenRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<Token>
 */
interface Tokens extends Entities
{
    public function oneByValueAndName(?string $value, string $name): ?Token;

    public function oneByValue(?string $value): ?Token;

    /** A token of this name and value that signs in (isLogginable). */
    public function oneSigningInByNameAndValue(string $name, ?string $value): ?Token;

    /** A token of this value that signs in (isLogginable): an API key. */
    public function oneSigningInByValue(?string $value): ?Token;
}
