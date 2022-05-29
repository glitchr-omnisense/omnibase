<?php

namespace Base\Filter\Advanced;

use enshrined\svgSanitize\Sanitizer;

use Base\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

class SanitizeFilter implements FilterInterface
{
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
        return $image; //$this->sanitizer->sanitize($image);
    }
}
