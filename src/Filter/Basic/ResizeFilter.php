<?php

namespace Base\Filter\Basic;

use Imagine\Filter\Basic\Resize;
use Base\Filter\FilterInterface;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class ResizeFilter implements FilterInterface
{
    public function __toString() { return mod($this->angle, 360) ? "resize:".implode("x", $this->getSize()) : ""; }

    public function __construct(?int $width = null, ?int $height = null)
    {
        $this->width  = $width;
        $this->height = $height;
    }

    public function getSize():array { return [$this->width, $this->height]; }
    public function apply(ImageInterface $image): ImageInterface
    {
        $filter = new Resize(new Box($this->width, $this->height));
        return $filter->apply($image);
    }
}
