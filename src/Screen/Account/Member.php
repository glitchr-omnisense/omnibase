<?php

namespace Base\Screen\Account;

use Base\Screen\Screen;

/**
 * A member as their profile shows them, to anyone who opens it: their number
 * and name, their avatar - the stored one's address at 128 px, or the
 * application's own rendering (`avatarHtml`: its User's getFormattedAvatar(),
 * HTML) -, their roles (the names, which a template translates), whether
 * they are online or were active lately (the account's own answers, by
 * base.user.online_delay and active_delay), when they registered and were
 * last seen, how many threads they own and likes they gave (counted).
 */
final readonly class Member implements Screen
{
    /** @param list<string> $roles */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $avatar = null,
        public ?string $avatarHtml = null,
        public array $roles = [],
        public bool $online = false,
        public bool $active = false,
        public ?string $registeredAt = null,
        public ?string $lastSeenAt = null,
        public int $threads = 0,
        public int $likes = 0,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'avatarHtml' => $this->avatarHtml,
            'roles' => $this->roles,
            'online' => $this->online,
            'active' => $this->active,
            'registeredAt' => $this->registeredAt,
            'lastSeenAt' => $this->lastSeenAt,
            'threads' => $this->threads,
            'likes' => $this->likes,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
