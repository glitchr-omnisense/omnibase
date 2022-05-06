<?php

namespace Base\Filter\Advanced;

use Base\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

class InterlaceFilter implements FilterInterface
{
    public function __toString() { return "interlace:".$this->mode; }

    public function __construct(string $mode = ImageInterface::INTERLACE_LINE)
    {
        $this->mode = $mode;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        $image->interlace($this->mode);
        return $image;
    }
}
