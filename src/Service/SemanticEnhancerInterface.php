<?php

namespace Base\Service;

interface SemanticEnhancerInterface
{
    public function highlight(string|array|null $strOrArray, array $attributes = []): string|array|null;
    public function highlightByWord(string $word, array $attributes = []);
}
