<?php

namespace Base\Service;

interface WysiwygEnhancerInterface
{
    public function supports(mixed $json): bool;

    public function render(mixed $json, array $options = []): string;
    public function highlightHeadings(mixed $json, array $options = [], ?int $maxLevel = null): mixed;
    public function getTableOfContents(mixed $json, ?int $maxLevel = null): mixed;
}
