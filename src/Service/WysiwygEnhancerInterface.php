<?php

namespace Base\Service;

/**
 *
 */
interface WysiwygEnhancerInterface
{
    public function supports(mixed $json): bool;

    public function render(mixed $json, array $options = []): string;

    public function getTableOfContents(mixed $json, ?int $maxLevel = null): mixed;

    public function highlightMentions(mixed $html, array $attrs = []): mixed;
    public function highlightHeadings(mixed $json, ?int $maxLevel = null, array $attr = []): mixed;
    public function highlightSemantics(mixed $json, null|array|string $words = null, array $attr = []): mixed;
}
