<?php

namespace Base\Filter;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

class WebpFilter implements FilterInterface
{
    public function apply(ImageInterface $image): ImageInterface
    {
        return $image;
    }
}