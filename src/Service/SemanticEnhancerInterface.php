<?php

namespace Base\Service;

interface SemanticEnhancerInterface
{
    public function highlight(string|array|null $strOrArray, array $attributes = [], null|array|string $words = null): string|array|null;
}
