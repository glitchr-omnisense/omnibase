<?php

namespace Base\Bridge\Symfony;

use Base\Contract\Texts;
use Base\Service\EditorEnhancerInterface;
use Base\Twig\Extension\HtmlTwigExtension;

/**
 * The Texts contract under Symfony (plan "omnibase agnostique", C5): the
 * pipeline of omnibase's `|wysiwyg` and `|wysiwyg_toc` filters itself
 * (mentions, links, media, headings, Editor.js's renderer or the HTML of the
 * older editor), so a screen's text is the HTML the templates printed.
 */
final class WysiwygTexts implements Texts
{
    public function __construct(
        private readonly HtmlTwigExtension $html,
        private readonly EditorEnhancerInterface $editor,
    ) {
    }

    public function isEditor(?string $text): bool
    {
        return null !== $text && '' !== $text && $this->editor->supports($text);
    }

    public function render(?string $text, array $options = []): string
    {
        // As the filter: nothing for null, the editor's empty frame for ''.
        return null === $text ? '' : (string) $this->html->renderWysiwyg($text, $options);
    }

    public function toc(?string $text, ?int $maxLevel = null): array
    {
        return null === $text || '' === $text ? [] : array_values($this->html->getTableOfContents($text, $maxLevel));
    }
}
