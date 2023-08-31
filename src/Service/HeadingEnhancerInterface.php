<?php

namespace Base\Service;

/**
 *
 */
interface HeadingEnhancerInterface
{
    public function highlight(string|array|null $strOrArray, ?int $maxLevel, array $attributes = []): mixed;
    public function toc(mixed $strOrArray, ?int $maxLevel): array;
}
