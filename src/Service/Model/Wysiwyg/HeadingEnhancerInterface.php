<?php

namespace Base\Service\Model\Wysiwyg;

/**
 *
 */
interface HeadingEnhancerInterface
{
    public function enhance(string|array|null $strOrArray, ?int $maxLevel, array $attributes = []): mixed;
    public function toc(mixed $strOrArray, ?int $maxLevel): array;
}
