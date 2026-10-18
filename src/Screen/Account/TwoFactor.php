<?php

namespace Base\Screen\Account;

use Base\Screen\Screen;

/**
 * An account's second factor on its settings page, as the security policy
 * lets its owner change it: whether the administrator requires one (and the
 * account still lacks it), the authenticator app - on or off, the address to
 * switch it on (when it may be), the action switching it off (when it may
 * be: on without it is locked by the administrator) -, the code by e-mail -
 * on or off, locked (on, the only factor left, and required), the action
 * toggling it (when not locked and the account may change its credentials) -,
 * the backup codes left (null while the app is off).
 */
final readonly class TwoFactor implements Screen
{
    public function __construct(
        public bool $mandatory = false,
        public bool $enrolmentRequired = false,
        public bool $totp = false,
        public ?string $totpEnable = null,
        public ?Action $totpDisable = null,
        public bool $email = false,
        public bool $emailLocked = false,
        public ?Action $emailToggle = null,
        public ?int $backupCodes = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'mandatory' => $this->mandatory,
            'enrolmentRequired' => $this->enrolmentRequired,
            'totp' => $this->totp,
            'totpEnable' => $this->totpEnable,
            'totpDisable' => $this->totpDisable?->toArray(),
            'email' => $this->email,
            'emailLocked' => $this->emailLocked,
            'emailToggle' => $this->emailToggle?->toArray(),
            'backupCodes' => $this->backupCodes,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
