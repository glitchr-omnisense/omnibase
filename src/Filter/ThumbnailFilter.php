<?php

namespace Base\Filter;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

class ThumbnailFilter extends ImageFilter implements FilterInterface
{
    public function apply(ImageInterface $image): ImageInterface
    {
        return parent::apply($image);
    }
}