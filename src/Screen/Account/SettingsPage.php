<?php

namespace Base\Screen\Account;

use Base\Screen\Confidential;
use Base\Screen\Shape;

/**
 * The account's settings (/settings): its name (null for nobody signed in:
 * the page shows its title alone), whether it is a demonstration account
 * (changing nothing of its credentials), its second factor (null where
 * two-factor is not available), its passkeys (null where passkeys are not)
 * and the identifier a new one registers, the sign-in providers shown (those
 * available, and the account's own), its latest sessions, its language (its
 * locale, `language`: the frame owns `locale`) and time zone. Its actions
 * carry their tokens: confidential.
 */
final readonly class SettingsPage implements Confidential
{
    /**
     * @param list<Passkey>|null $passkeys
     * @param list<Provider>     $providers
     * @param list<Session>      $sessions
     */
    public function __construct(
        public ?string $name = null,
        public bool $demo = false,
        public ?TwoFactor $twoFactor = null,
        public ?array $passkeys = null,
        public ?string $identifier = null,
        public array $providers = [],
        public array $sessions = [],
        public ?string $language = null,
        public ?string $timezone = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'demo' => $this->demo,
            'twoFactor' => $this->twoFactor?->toArray(),
            'passkeys' => null === $this->passkeys ? null : Shape::export($this->passkeys),
            'identifier' => $this->identifier,
            'providers' => Shape::export($this->providers),
            'sessions' => Shape::export($this->sessions),
            'language' => $this->language,
            'timezone' => $this->timezone,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
