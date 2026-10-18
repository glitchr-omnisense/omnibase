<?php

namespace Base\Screen\Account;

use Base\Screen\Screen;

/**
 * A notification in the member's list: the bell's item
 * (NotificationController::serialize(): id, title - else the subject -, text
 * without its tags - null when it has none -, address, back-office address,
 * importance, read or not, when it was sent) and its `group` - `today`,
 * `yesterday`, `week` or `earlier`, by the screen's clock in the page's time
 * zone, so that no template compares a moment.
 */
final readonly class Notification implements Screen
{
    public const GROUPS = ['today', 'yesterday', 'week', 'earlier'];

    public function __construct(
        public int $id,
        public string $title = '',
        public ?string $content = null,
        public ?string $url = null,
        public ?string $adminUrl = null,
        public ?string $importance = null,
        public bool $isRead = false,
        public ?string $sentAt = null,
        public string $group = 'earlier',
    ) {
        if (!\in_array($group, self::GROUPS, true)) {
            throw new \InvalidArgumentException(sprintf('A notification\'s group is one of "%s", not "%s".', implode('", "', self::GROUPS), $group));
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'url' => $this->url,
            'adminUrl' => $this->adminUrl,
            'importance' => $this->importance,
            'isRead' => $this->isRead,
            'sentAt' => $this->sentAt,
            'group' => $this->group,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
