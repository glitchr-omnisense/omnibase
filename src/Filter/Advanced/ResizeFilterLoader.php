<?php

namespace Base\Filter\Advanced;

use Imagine\Filter\Basic\Resize;
use Imagine\Filter\FilterInterface;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class ResizeFilterLoader implements FilterInterface
{
    public function __construct(?int $width = null, ?int $height = null)
    {
        $this->width  = $width;
        $this->height = $height;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        $filter = new Resize(new Box($this->width, $this->height));
        return $filter->apply($image);
    }
}
