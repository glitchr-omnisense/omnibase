<?php

namespace Base\Screen;

/**
 * Another story, as a page points to it (the one before, after, to read as
 * well; a member's latest on their profile): its title, address and state;
 * `publishedAt` where a page shows the date, left out of the props where it
 * shows none.
 */
final readonly class Link implements Screen
{
    public function __construct(
        public string $title,
        public string $url,
        public string $state,
        public ?string $publishedAt = null,
    ) {
    }

    public function toArray(): array
    {
        return ['title' => $this->title, 'url' => $this->url, 'state' => $this->state]
            + (null === $this->publishedAt ? [] : ['publishedAt' => $this->publishedAt]);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
