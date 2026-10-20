<?php

namespace Base\Auth;

use Omniauth\Contract\BackupCodes;

/** The backup codes: user.backupCodes, a JSON list of password_hash()'s, as User::setBackupCodes() writes it. */
class UserBackupCodes extends UserStore implements BackupCodes
{
    public function hashes(object $account): array
    {
        return array_values(array_filter($this->user($account)->getBackupCodeHashes(), 'is_string'));
    }

    public function replace(object $account, array $hashes): void
    {
        $this->user($account)->setBackupCodeHashes(array_values($hashes));
        $this->save();
    }
}
