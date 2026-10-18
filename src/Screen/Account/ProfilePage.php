<?php

namespace Base\Screen\Account;

use Base\Screen\Link;
use Base\Screen\Screen;
use Base\Screen\Shape;

/**
 * A member's profile (/profile/{id}): the member, whether the viewer is that
 * member, their five latest published threads (newest first, each dated by
 * its publication, else its creation), the account's summary - only for its
 * owner or an administrator, null for anyone else -, and the addresses its
 * owner (or an administrator, for the edit) follows from there.
 *
 * What a visitor's props carry is what the page shows that visitor: the
 * header (number, name, avatar, roles, presence, dates, counts) and the
 * threads; the account's summary and the owner's addresses stay null.
 */
final readonly class ProfilePage implements Screen
{
    /** @param list<Link> $recent */
    public function __construct(
        public Member $member,
        public bool $own = false,
        public array $recent = [],
        public ?AccountSummary $account = null,
        public ?string $editUrl = null,
        public ?string $settingsUrl = null,
        public ?string $logoutUrl = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'member' => $this->member->toArray(),
            'own' => $this->own,
            'recent' => Shape::export($this->recent),
            'account' => $this->account?->toArray(),
            'editUrl' => $this->editUrl,
            'settingsUrl' => $this->settingsUrl,
            'logoutUrl' => $this->logoutUrl,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
