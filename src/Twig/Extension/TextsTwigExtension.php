<?php

namespace Base\Twig\Extension;

use Base\Contract\Texts;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * A stored text in a template: `|wysiwyg` (an Editor.js document or the HTML
 * of an older editor, rendered - mentions, links, media, headings anchored;
 * printed with |raw) and `|wysiwyg_toc` (its headings), on the Texts
 * contract.
 *
 * Framework-free (audit "lesringostars face au master plan", R24): Symfony
 * registers it with Base\Bridge\Symfony\WysiwygTexts (HtmlTwigExtension's
 * own pipeline, the HTML of before), Laravel (omnibridge/laravel) with its
 * WysiwygTexts on the page's environment - the same filters, under the same
 * names (they were HtmlTwigExtension's).
 */
final class TextsTwigExtension extends AbstractExtension
{
    public function __construct(private readonly Texts $texts)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('wysiwyg', [$this, 'render']),
            new TwigFilter('wysiwyg_toc', [$this, 'toc']),
        ];
    }

    /** @param array<string, mixed> $options headings, semantics, media, row_attr, hydrate */
    public function render(?string $htmlOrJson, array $options = []): string
    {
        return $this->texts->render($htmlOrJson, $options);
    }

    /** @return list<array{tag: string, slug: string, title: string}> */
    public function toc(?string $htmlOrJson, ?int $maxLevel = null): array
    {
        return $this->texts->toc($htmlOrJson, $maxLevel);
    }
}
