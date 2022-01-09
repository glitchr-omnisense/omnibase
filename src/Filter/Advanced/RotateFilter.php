<?php

namespace Base\Filter\Advanced;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

class RotateFilter implements FilterInterface
{
    public function __construct(int $angle = 0)
    {
        $this->angle = $angle;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        return 0 === $this->angle ? $image : $image->rotate($this->angle);
    }
}
