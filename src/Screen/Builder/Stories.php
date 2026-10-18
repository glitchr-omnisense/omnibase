<?php

namespace Base\Screen\Builder;

use Base\Contract\Pictures;
use Base\Contract\Runtime;
use Base\Contract\Texts;
use Base\Entity\Thread;
use Base\Entity\Thread\Comment as CommentEntity;
use Base\Screen\Author;
use Base\Screen\Card;
use Base\Screen\Comment;
use Base\Screen\Link;
use Base\Screen\Reply;
use Base\Screen\Shape;
use Base\Screen\Story;
use Base\Screen\StoryText;
use Base\Screen\Tag;
use Psr\Clock\ClockInterface;

/**
 * omnibase's threads as view models (plan "omnibase agnostique", C5): a
 * thread on a list (Card), on its own page (Story), pointed to (Link), its
 * text (StoryText), its tags, taxa and owners, the comments left on it.
 * Framework-free: the addresses come from the caller (a closure per kind of
 * link, made with the Urls contract or the framework's router), the images
 * from the Pictures contract, the texts from the Texts contract, "now" from a
 * PSR-20 clock (the one given, else Runtime's); the entities are read through their getters.
 *
 * What a screen counts or shows beyond that (likes, a count of comments, a
 * site's own reading time) it passes in; what it leaves out stays null.
 */
final class Stories
{
    public function __construct(
        private readonly ?Texts $texts = null,
        private readonly ?Pictures $pictures = null,
        private readonly ?ClockInterface $clock = null,
        /** How lately a story or a comment was published to be recent: a relative date, before now. */
        private readonly string $recently = '-2 days',
    ) {
    }

    /**
     * A thread on a list.
     *
     * @param \Closure(object $tag): string|null   $tagUrl   the address listing a tag's stories
     * @param \Closure(object $taxon): string|null $taxonUrl the address listing a taxon's
     * @param string|null                          $cover    the stored image (null: none); given, the card's cover is its address at $coverWidth, '' when it has none
     */
    public function card(
        Thread $thread,
        string $url,
        ?\Closure $tagUrl = null,
        ?\Closure $taxonUrl = null,
        ?string $cover = null,
        ?int $coverWidth = null,
        ?int $readingMinutes = null,
        ?int $likes = null,
        ?string $likeUrl = null,
        ?int $comments = null,
    ): Card {
        $publishedAt = Shape::moment($thread->getPublishedAt());

        return new Card(
            slug: (string) $thread->getSlug(),
            title: (string) ($thread->getTitle() ?? $thread->getSlug()),
            url: $url,
            excerpt: $thread->getExcerpt(),
            hasContent: '' !== (string) $thread->getContent(),
            publishedAt: $publishedAt,
            readingMinutes: $readingMinutes ?? $this->readingMinutes($thread),
            authors: $this->owners($thread),
            tags: $tagUrl ? $this->tags($thread->getTags(), $tagUrl) : [],
            likes: $likes,
            likeUrl: $likeUrl,
            comments: $comments,
            headline: $thread->getHeadline(),
            cover: $this->cover($cover, $coverWidth),
            taxa: $taxonUrl ? $this->tags($thread->getTaxa(), $taxonUrl) : [],
            recent: $this->isRecent($publishedAt),
        );
    }

    /**
     * A thread on its own page: its card's fields, its text through Texts.
     *
     * @param array<string, mixed> $textOptions the options of `|wysiwyg`
     * @param list<string>         $audio       its sounds' addresses
     */
    public function story(
        Thread $thread,
        string $url,
        ?\Closure $tagUrl = null,
        ?\Closure $taxonUrl = null,
        ?string $cover = null,
        ?int $coverWidth = null,
        ?int $readingMinutes = null,
        array $textOptions = [],
        array $audio = [],
        ?int $likes = null,
        ?string $likeUrl = null,
        bool $commentable = false,
        ?int $comments = null,
    ): Story {
        $card = $this->card($thread, $url, $tagUrl, $taxonUrl, $cover, $coverWidth, $readingMinutes);

        return new Story(
            id: (int) $thread->getId(),
            slug: $card->slug,
            title: $card->title,
            url: $url,
            state: self::state($thread->getState()),
            headline: $thread->getHeadline() ?: null,
            excerpt: $card->excerpt,
            text: $this->text($thread->getContent(), $textOptions),
            publishedAt: $card->publishedAt,
            readingMinutes: $card->readingMinutes,
            authors: $card->authors,
            tags: $card->tags,
            audio: $audio,
            likes: $likes,
            likeUrl: $likeUrl,
            commentable: $commentable,
            comments: $comments,
            cover: $card->cover,
            taxa: $card->taxa,
            recent: $card->recent,
        );
    }

    /** A thread pointed to (the one before, after, to read as well); null for none. */
    public function link(?Thread $thread, ?string $url): ?Link
    {
        if (null === $thread || null === $url) {
            return null;
        }

        return new Link((string) ($thread->getTitle() ?? $thread->getSlug()), $url, self::state($thread->getState()));
    }

    /**
     * A stored text as its page shows it, through the Texts contract.
     *
     * @param array<string, mixed> $options the options of `|wysiwyg`
     */
    public function text(?string $content, array $options = []): StoryText
    {
        if (null === $content) {
            return new StoryText();
        }
        $texts = $this->texts ?? throw new \LogicException('A story\'s text needs the Texts contract.');

        return new StoryText($texts->render($content, $options), $texts->isEditor($content), array_values($texts->toc($content)));
    }

    /**
     * Tags or taxa (any entity with a slug and a label: Base\Entity\Thread\Tag, Taxon).
     *
     * @param iterable<object>               $tags
     * @param \Closure(object $tag): string $url
     * @param string|null                    $current the slug of the one shown, when the screen marks it
     *
     * @return list<Tag>
     */
    public function tags(iterable $tags, \Closure $url, ?string $current = null, bool $marked = false): array
    {
        $list = [];
        foreach ($tags as $tag) {
            $slug = (string) $tag->getSlug();
            $list[] = new Tag(
                slug: $slug,
                label: (string) ($tag->getLabel() ?? $slug),
                url: $url($tag),
                color: method_exists($tag, 'getColor') ? $tag->getColor() : null,
                current: $marked ? $slug === $current : null,
            );
        }

        return $list;
    }

    /** @return list<Author> the thread's owners, in their order, by their names */
    public function owners(Thread $thread): array
    {
        $authors = [];
        foreach ($thread->getOwners() as $owner) {
            $authors[] = new Author((string) $owner);
        }

        return $authors;
    }

    /**
     * Comments left on a thread (Base\Entity\Thread\Comment, or none for a
     * visitors' book), each with its visible replies: signed by the account's
     * name or the name typed, the website typed as the author's page, `staff`
     * for an account holding a role besides ROLE_USER.
     *
     * @param iterable<CommentEntity> $comments
     *
     * @return list<Comment>
     */
    public function comments(iterable $comments): array
    {
        $list = [];
        foreach ($comments as $comment) {
            $publishedAt = Shape::moment($comment->getCreatedAt());
            $list[] = new Comment(
                id: $comment->getId(),
                uuid: null,
                publishedAt: $publishedAt,
                authors: [$this->commenter($comment)],
                content: $comment->getContent(),
                replies: array_map(fn (CommentEntity $reply) => new Reply(
                    id: $reply->getId(),
                    uuid: null,
                    publishedAt: $at = Shape::moment($reply->getCreatedAt()),
                    authors: [$this->commenter($reply)],
                    content: $reply->getContent(),
                    recent: $this->isRecent($at),
                ), $comment->getVisibleReplies()),
                recent: $this->isRecent($publishedAt),
            );
        }

        return $list;
    }

    /** Whether a moment is within the recent window before now (the screen's clock). */
    public function isRecent(?string $moment): bool
    {
        if (null === $moment) {
            return false;
        }
        $now = ($this->clock ?? Runtime::clock())->now();
        $moment = new \DateTimeImmutable($moment);

        return $moment <= $now && $moment >= $now->modify($this->recently);
    }

    private function commenter(CommentEntity $comment): Author
    {
        $roles = $comment->getAuthor()?->getRoles() ?? [];

        return new Author(
            name: $comment->getDisplayName(),
            profile: $comment->getWebsite() ?: null,
            staff: [] !== array_filter($roles, static fn ($role) => 'ROLE_USER' !== $role),
        );
    }

    private function cover(?string $image, ?int $width): ?string
    {
        if (null === $image) {
            return null;
        }

        return (string) ($this->pictures ?? throw new \LogicException('A cover needs the Pictures contract.'))->picture($image, $width);
    }

    private function readingMinutes(Thread $thread): int
    {
        return method_exists($thread, 'getReadingMinutes') ? (int) $thread->getReadingMinutes() : 0;
    }

    /** @internal a thread's state as the screens carry it: an enumeration's value, else the string */
    public static function state(mixed $state): string
    {
        return $state instanceof \BackedEnum ? (string) $state->value : (string) $state;
    }
}
