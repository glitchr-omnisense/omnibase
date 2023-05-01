<?php

namespace Base\Service;

/**
 *
 */
interface SemanticEnhancerInterface
{
    public function highlight(string|array|null $strOrArray, null|array|string $words = null, array $attributes = []): string|array|null;
}
