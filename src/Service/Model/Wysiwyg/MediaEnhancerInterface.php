<?php

namespace Base\Service\Model\Wysiwyg;

use Base\Imagine\FilterInterface;

/**
 *
 */
interface MediaEnhancerInterface
{
    public function enhance(string|array|null $strOrArray, array $config = [], FilterInterface|array $filters = [], array $attributes = []): string|array|null;
}
