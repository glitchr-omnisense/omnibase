<?php

namespace Base\Contract;

/**
 * A stored text as a page shows it (plan "omnibase agnostique", C5): an
 * Editor.js document or the HTML of an older editor, rendered - mentions,
 * links, media, headings anchored - and its table of contents. What
 * omnibase's `|wysiwyg` and `|wysiwyg_toc` filters answer.
 *
 * The Fondation's own contract, reached by Runtime::texts() or given to a
 * screen's builder. Under Symfony, Base\Bridge\Symfony\WysiwygTexts: the
 * filters' own pipeline, so a screen's HTML is the page's of before.
 */
interface Texts
{
    /** Whether the text is an Editor.js document (rather than HTML). */
    public function isEditor(?string $text): bool;

    /**
     * The text rendered, '' for none (null).
     *
     * @param array<string, mixed> $options those of `|wysiwyg` (headings, media, semantics...)
     */
    public function render(?string $text, array $options = []): string;

    /**
     * The text's headings, in its order.
     *
     * @return list<array{tag: string, slug: string, title: string}>
     */
    public function toc(?string $text, ?int $maxLevel = null): array;
}
