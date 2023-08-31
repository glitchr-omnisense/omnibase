<?php

namespace Base\Service;

/**
 *
 */
interface MentionEnhancerInterface
{
    public function extract(string|array|null $strOrArray, array $attributes = []): array;
    public function highlight(string|array|null $strOrArray, array $attributes = []): string|array|null;
}
