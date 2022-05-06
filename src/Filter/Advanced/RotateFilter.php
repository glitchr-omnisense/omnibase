<?php

namespace Base\Filter\Advanced;

use Base\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

class RotateFilter implements FilterInterface
{
    public function __toString() { return mod($this->angle, 360) ? "rot:".$this->angle : ""; }
    public function __construct(int $angle = 0)
    {
        $this->angle = $angle;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        return 0 === $this->angle ? $image : $image->rotate($this->angle);
    }
}
