<?php

namespace Base\Service;

/**
 *
 */
interface MentionEnhancerInterface
{
    public function highlight(string|array|null $strOrArray, array $attributes = []): string|array|null;
}
