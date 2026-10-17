<?php

namespace Base\Screen;

/**
 * An answer to a comment (lesringostars' replies): its id or uuid (at least
 * one: a front end keys and anchors on the uuid, else the id), date,
 * authors, text, whether it was deleted, and whether it is recent by the
 * screen's clock.
 */
final readonly class Reply implements Screen
{
    /** @param list<Author> $authors */
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public ?string $publishedAt = null,
        public array $authors = [],
        public ?string $content = null,
        public bool $deleted = false,
        public bool $recent = false,
    ) {
        Comment::identified($id, $uuid);
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'publishedAt' => $this->publishedAt,
            'authors' => Shape::export($this->authors),
            'content' => $this->content,
            'deleted' => $this->deleted,
            'id' => $this->id,
            'recent' => $this->recent,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
