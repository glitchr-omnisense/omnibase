<?php

namespace Base\Screen\Account;

use Base\Screen\Screen;

/** A passkey of the account: its number, its label (null for none), when it was added and last used, the actions renaming and removing it. */
final readonly class Passkey implements Screen
{
    public function __construct(
        public int $id,
        public Action $rename,
        public Action $delete,
        public ?string $label = null,
        public ?string $addedAt = null,
        public ?string $lastUsedAt = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'addedAt' => $this->addedAt,
            'lastUsedAt' => $this->lastUsedAt,
            'rename' => $this->rename->toArray(),
            'delete' => $this->delete->toArray(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
