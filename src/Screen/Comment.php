<?php

namespace Base\Screen;

/**
 * A comment (lesringostars' comments): its id or uuid (at least one: a front
 * end keys and anchors on the uuid, else the id), slug, date, authors, text
 * (as written: a template breaks its lines), the address to follow it, its
 * replies, and whether it is recent by the screen's clock.
 */
final readonly class Comment implements Screen
{
    /**
     * @param list<Author> $authors
     * @param list<Reply>  $replies
     */
    public function __construct(
        public ?int $id,
        public ?string $uuid,
        public ?string $slug = null,
        public ?string $publishedAt = null,
        public array $authors = [],
        public ?string $content = null,
        public ?string $followUrl = null,
        public array $replies = [],
        public bool $recent = false,
    ) {
        self::identified($id, $uuid);
    }

    /** @internal a comment or a reply names itself by an id, a uuid, or both */
    public static function identified(?int $id, ?string $uuid): void
    {
        if (null === $id && (null === $uuid || '' === $uuid)) {
            throw new \InvalidArgumentException('A comment or a reply has an id or a uuid, at least one.');
        }
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'publishedAt' => $this->publishedAt,
            'authors' => Shape::export($this->authors),
            'content' => $this->content,
            'followUrl' => $this->followUrl,
            'replies' => Shape::export($this->replies),
            'id' => $this->id,
            'recent' => $this->recent,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
