<?php

namespace Base\Screen;

/** Another story, as a page points to it (the one before, after, to read as well): its title, address and state. */
final readonly class Link implements Screen
{
    public function __construct(
        public string $title,
        public string $url,
        public string $state,
    ) {
    }

    public function toArray(): array
    {
        return ['title' => $this->title, 'url' => $this->url, 'state' => $this->state];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
