<?php

namespace Base\Filter;

use Imagine\Filter\Basic\Thumbnail;
use Imagine\Filter\FilterInterface;
use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;

class ThumbnailFilter extends ImageFilter implements FilterInterface
{
    public function __construct(string $path, array $filters = [], BoxInterface $box, $mode = ImageInterface::THUMBNAIL_INSET, $resampling = ImageInterface::FILTER_UNDEFINED)
    {
        $width  = $box->getWidth();
        $height = $box->getHeight();

        $ratio  = $width/$height;
        if( $ratio > 1) $height = $width/$ratio;
        else $width = $height*$ratio;

        $path = $path."_".($width."x".$height);
        $filter[] = new Thumbnail(new Box($width, $height), $mode, $resampling);

        parent::__construct($path, $filters);
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        return parent::apply($image);
    }
}