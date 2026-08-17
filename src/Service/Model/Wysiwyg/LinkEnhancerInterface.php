<?php

namespace Base\Service\Model\Wysiwyg;

interface LinkEnhancerInterface
{
    public function enhance(string|array|null $strOrArray, array $attributes = []): string|array|null;
}
