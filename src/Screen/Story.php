<?php

namespace Base\Screen;

/**
 * A story on its own page (lesringostars' StoryPage `article`): what its card
 * says, with its id, its text (StoryText's html, editor and toc, flattened as
 * there), its sounds' addresses, whether it takes comments, and its state.
 * The cover is at the width the page shows it.
 */
readonly class Story implements Screen
{
    /**
     * @param list<Author> $authors
     * @param list<Tag>    $tags
     * @param list<Tag>    $taxa
     * @param list<string> $audio
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public string $url,
        public string $state,
        public ?string $headline = null,
        public ?string $excerpt = null,
        public StoryText $text = new StoryText(),
        public ?string $publishedAt = null,
        public int $readingMinutes = 0,
        public array $authors = [],
        public array $tags = [],
        public array $audio = [],
        public ?int $likes = null,
        public ?string $likeUrl = null,
        public bool $commentable = false,
        public ?int $comments = null,
        public ?string $cover = null,
        public array $taxa = [],
        public bool $recent = false,
    ) {
    }

    /** The text's HTML (Twig: story.html, as the props say it). */
    public function getHtml(): string
    {
        return $this->text->html;
    }

    public function isEditor(): bool
    {
        return $this->text->editor;
    }

    /** @return list<array{tag: string, slug: string, title: string}> */
    public function getToc(): array
    {
        return $this->text->toc;
    }

    public function hasContent(): bool
    {
        return '' !== $this->text->html;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'headline' => $this->headline,
            'excerpt' => $this->excerpt,
            'html' => $this->text->html,
            'editor' => $this->text->editor,
            'toc' => $this->text->toc,
            'publishedAt' => $this->publishedAt,
            'readingMinutes' => $this->readingMinutes,
            'authors' => Shape::export($this->authors),
            'tags' => Shape::export($this->tags),
            'audio' => $this->audio,
            'likes' => $this->likes,
            'likeUrl' => $this->likeUrl,
            'commentable' => $this->commentable,
            'comments' => $this->comments,
            'state' => $this->state,
            'url' => $this->url,
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
