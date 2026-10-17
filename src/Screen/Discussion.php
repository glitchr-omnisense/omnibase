<?php

namespace Base\Screen;

/**
 * The comments of a page and whether one may write (lesringostars' page
 * `comments` and `hasMoreComments`, here `comments` and `hasMore`): one model,
 * because the block is shown on pages that are no story too (a visitors' book,
 * a host's home). `open`: a comment may be written here (the site's settings,
 * the story's, the visitor); `canReply`: an answer to one may; `signIn`: the
 * address of the sign-in page, for whoever may not; `flashes`: the messages
 * left for this page, already read.
 */
final readonly class Discussion implements Screen
{
    /**
     * @param list<Comment> $comments
     * @param list<string>  $flashes
     */
    public function __construct(
        public array $comments = [],
        public bool $hasMore = false,
        public bool $open = false,
        public bool $canReply = false,
        public ?string $signIn = null,
        public array $flashes = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'comments' => Shape::export($this->comments),
            'hasMore' => $this->hasMore,
            'open' => $this->open,
            'canReply' => $this->canReply,
            'signIn' => $this->signIn,
            'flashes' => $this->flashes,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
