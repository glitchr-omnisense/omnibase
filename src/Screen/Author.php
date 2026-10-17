<?php

namespace Base\Screen;

/**
 * Who wrote a story, a comment, a reply (lesringostars' authors): a name, the
 * address of their page (a profile, a comment's website), an avatar's
 * address; `staff` where a screen marks the site's own people (the blog's
 * "author" pill), left out of the props where it does not.
 */
final readonly class Author implements Screen
{
    public function __construct(
        public string $name,
        public ?string $profile = null,
        public ?string $avatar = null,
        public ?bool $staff = null,
    ) {
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'profile' => $this->profile, 'avatar' => $this->avatar] + (null === $this->staff ? [] : ['staff' => $this->staff]);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
