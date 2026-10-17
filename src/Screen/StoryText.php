<?php

namespace Base\Screen;

/**
 * A story's text as its page shows it (lesringostars' StoryText::render()):
 * the HTML rendered, whether it was an Editor.js document, and its table of
 * contents (each heading's tag, anchor and title, in the text's order).
 */
final readonly class StoryText implements Screen
{
    /** @param list<array{tag: string, slug: string, title: string}> $toc */
    public function __construct(
        public string $html = '',
        public bool $editor = false,
        public array $toc = [],
    ) {
    }

    public function toArray(): array
    {
        return ['html' => $this->html, 'editor' => $this->editor, 'toc' => $this->toc];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
