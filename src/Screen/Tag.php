<?php

namespace Base\Screen;

/**
 * A tag, a taxon (lesringostars' tags): its slug, label, colour, icon and the
 * address that lists what carries it; `count` in a cloud of tags, `current`
 * in a navigation by tag (the one shown), each left out of the props where a
 * screen has none.
 */
final readonly class Tag implements Screen
{
    public function __construct(
        public string $slug,
        public string $label,
        public string $url,
        public ?string $color = null,
        public ?string $icon = null,
        public ?int $count = null,
        public ?bool $current = null,
    ) {
    }

    public function toArray(): array
    {
        return ['slug' => $this->slug, 'label' => $this->label, 'color' => $this->color, 'icon' => $this->icon, 'url' => $this->url]
            + (null === $this->count ? [] : ['count' => $this->count])
            + (null === $this->current ? [] : ['current' => $this->current]);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
