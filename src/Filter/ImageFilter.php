<?php

namespace Base\Filter;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

class WebpFilter extends ImageFilter implements FilterInterface
{
    public function __construct()
    {

    }

    public function apply(ImageInterface $image): ImageInterface
    {
        return parent::apply($image);
    }
}