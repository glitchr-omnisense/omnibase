<?php

namespace Base\Filter\Advanced;

use Imagine\Filter\Basic\Crop;
use Imagine\Filter\FilterInterface;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;

class CropFilterLoader implements FilterInterface
{
    public function __construct(int $x = 0, int $y = 0, ?int $width = null, ?int $height = null)
    {
        $this->x = $x ?? 0;
        $this->y = $y ?? 0;

        $this->width  = $width  ?? 0;
        $this->height = $height ?? 0;

    }

    public function apply(ImageInterface $image)
    {
        $filter = new Crop(
            new Point($this->x,     $this->y),
            new Box  ($this->width, $this->height)
        );

        return $filter->apply($image);
    }
}
