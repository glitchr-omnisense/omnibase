<?php

namespace Base\Imagine\Filter\Advanced;

use enshrined\svgSanitize\Sanitizer;

use Base\Imagine\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 *
 */
class SanitizeFilter implements FilterInterface
{
    private Sanitizer $sanitizer;

    /**
     * @return string
     */
    public function __toString()
    {
        return "sanitize";
    }

    public function __construct()
    {
        $this->sanitizer = new Sanitizer();
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        return $this->sanitizer->sanitize($image);
    }
}
