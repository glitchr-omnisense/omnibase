<?php

namespace Base\Screen\Account;

use Base\Screen\Screen;

/**
 * An account's security at a glance, on its profile, for its owner or an
 * administrator: whether it has a second factor (null where two-factor is not
 * available on the site), how many passkeys (null where passkeys are not -
 * never 0 for "not counted"), the provider it signs in with (`google`), its
 * language and time zone.
 */
final readonly class AccountSummary implements Screen
{
    public function __construct(
        public ?bool $twoFactor = null,
        public ?int $passkeys = null,
        public ?string $identityProvider = null,
        public ?string $locale = null,
        public ?string $timezone = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'twoFactor' => $this->twoFactor,
            'passkeys' => $this->passkeys,
            'identityProvider' => $this->identityProvider,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
