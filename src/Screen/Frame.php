<?php

namespace Base\Screen;

/**
 * What every page shows around its screen (lesringostars' App\Screen\Frame):
 * the page's language, the site, the addresses of the menus, the visitor
 * signed in and the colour scheme, and what a site adds of its own (`extra`:
 * lesringostars' destination, destinations, last article, tags, map), merged
 * in its props between `site` and `links`. Given per request by a
 * ContextProvider.
 *
 * `host` (the scheme, host and base path the page was asked at) serves the
 * absolute addresses and the feed; it is not in the props.
 */
final readonly class Frame implements Screen
{
    /** The keys of the frame's own fields, which a site's extra may not take. */
    public const RESERVED = ['locale', 'site', 'links', 'viewer', 'theme'];

    /**
     * @param array<string, string|array<string, string>|null> $links by name; `locales` the address of each language
     * @param array{scheme?: string}                            $theme
     * @param array<string, mixed>                              $extra the site's own fields
     */
    public function __construct(
        public string $locale,
        public Site $site = new Site(),
        public array $links = [],
        public ?Viewer $viewer = null,
        public array $theme = [],
        public array $extra = [],
        public string $host = '',
    ) {
        if ($taken = array_intersect(array_keys($extra), self::RESERVED)) {
            throw new \InvalidArgumentException(sprintf('A frame\'s extra fields may not override its own: "%s".', implode('", "', $taken)));
        }
    }

    /** A copy with the site's own fields added (a site's provider decorating the core's). */
    public function with(array $extra): self
    {
        return new self($this->locale, $this->site, $this->links, $this->viewer, $this->theme, $extra + $this->extra, $this->host);
    }

    public function toArray(): array
    {
        return ['locale' => $this->locale, 'site' => $this->site->toArray()]
            + Shape::export($this->extra)
            + ['links' => $this->links, 'viewer' => $this->viewer?->toArray(), 'theme' => $this->theme];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
