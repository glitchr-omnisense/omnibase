<?php

namespace Base\Screen;

/**
 * A story on a list (lesringostars' BlogPage::card()): its slug, title,
 * excerpt, date, reading time, authors and tags, its address. `likes`,
 * `likeUrl` and `comments` are null where the screen counts none (never 0:
 * "none counted" and "zero" stay apart). Added for omnibase's blog: the line
 * under the title (`headline`), the cover's address at the width the screen
 * shows it, the taxa, and `recent` - published lately, by the screen's clock
 * (a template compares no moment: it reads this).
 */
readonly class Card implements Screen
{
    /**
     * @param list<Author> $authors
     * @param list<Tag>    $tags
     * @param list<Tag>    $taxa
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $url,
        public ?string $excerpt = null,
        public bool $hasContent = false,
        public ?string $publishedAt = null,
        public int $readingMinutes = 0,
        public array $authors = [],
        public array $tags = [],
        public ?int $likes = null,
        public ?string $likeUrl = null,
        public ?int $comments = null,
        public ?string $headline = null,
        public ?string $cover = null,
        public array $taxa = [],
        public bool $recent = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'hasContent' => $this->hasContent,
            'publishedAt' => $this->publishedAt,
            'readingMinutes' => $this->readingMinutes,
            'authors' => Shape::export($this->authors),
            'tags' => Shape::export($this->tags),
            'likes' => $this->likes,
            'likeUrl' => $this->likeUrl,
            'comments' => $this->comments,
            'url' => $this->url,
            'headline' => $this->headline,
            'cover' => $this->cover,
            'taxa' => Shape::export($this->taxa),
            'recent' => $this->recent,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
