<?php

namespace Base\Service;

use Base\Imagine\FilterInterface;

/**
 *
 */
interface WysiwygEnhancerInterface
{
    public function supports(mixed $json): bool;

    public function render(mixed $json, array $options = []): string;

    public function getTableOfContents(mixed $json, ?int $maxLevel = null): mixed;

    public function enhanceMentions(mixed $html, array $attrs = []): mixed;
    public function enhanceMedia(mixed $html, array $config = [], FilterInterface|array $filters = [], array $attrs = []): mixed;
    public function enhanceHeadings(mixed $json, ?int $maxLevel = null, array $attr = []): mixed;
    public function enhanceSemantics(mixed $json, null|array|string $words = null, array $attr = []): mixed;
}
