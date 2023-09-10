<?php

namespace Base\Service\Model\Wysiwyg;

/**
 *
 */
interface SemanticEnhancerInterface
{
    public function enhance(string|array|null $strOrArray, null|array|string $words = null, array $attributes = []): string|array|null;
}
