<?php

namespace Base\Screen;

/**
 * The visitor signed in, as a page may show them: an id, a name, an avatar's
 * address. Nothing else (no roles: what a screen needs of the visitor, its
 * builder decides - may they reply, is the form open).
 */
final readonly class Viewer implements Screen
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $avatar = null,
    ) {
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'avatar' => $this->avatar];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
