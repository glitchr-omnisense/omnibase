<?php

namespace Base\Screen\Account;

use Base\Screen\Screen;

/**
 * One of the account's sessions (a connection): its number, the browser's
 * own string and a readable label ("Safari - macOS"), the addresses it came
 * from, when it was last seen, whether it is still open, and the action
 * signing it out (while it is).
 */
final readonly class Session implements Screen
{
    /** @param list<string> $ips */
    public function __construct(
        public int $id,
        public ?string $agent = null,
        public ?string $label = null,
        public array $ips = [],
        public ?string $updatedAt = null,
        public bool $valid = false,
        public ?Action $logout = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'agent' => $this->agent,
            'label' => $this->label,
            'ips' => $this->ips,
            'updatedAt' => $this->updatedAt,
            'valid' => $this->valid,
            'logout' => $this->logout?->toArray(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
