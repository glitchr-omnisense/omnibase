<?php

namespace Base\Repository\User;

use Base\Database\Repository\Entities;
use Base\Entity\User\Passkey;

/**
 * The accounts' passkeys (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): PasskeyRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<Passkey>
 */
interface Passkeys extends Entities
{
    /** The passkey of that credential id (its raw bytes), as Base\Auth\UserPasskeys asks it (step F2d). */
    public function findOneByCredential(string $publicKeyCredentialId): ?Passkey;
}
