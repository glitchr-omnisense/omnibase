<?php

namespace Base\Screen\Account;

use Base\Screen\Screen;

/** A provider one may sign in with (`google`, "Google"): whether the account signs in with it, the address linking it (while it does not). */
final readonly class Provider implements Screen
{
    public function __construct(
        public string $name,
        public string $label,
        public bool $connected = false,
        public ?string $connectUrl = null,
    ) {
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'label' => $this->label, 'connected' => $this->connected, 'connectUrl' => $this->connectUrl];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
