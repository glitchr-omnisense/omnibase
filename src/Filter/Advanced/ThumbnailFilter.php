<?php

namespace Base\Filter\Advanced;

use Imagine\Filter\FilterInterface;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class ThumbnailFilter implements FilterInterface
{
    public function __construct(?int $width = null, ?int $height = null, $mode = ImageInterface::THUMBNAIL_INSET, $filter = ImageInterface::FILTER_UNDEFINED)
    {
        $this->width  = $width;
        $this->height = $height;
        $this->mode   = $mode;
        $this->filter = $filter;
    }

    public function __toString() { return ($this->width ?? "auto")."x".($this->height ?? "auto"); }
    public function apply(ImageInterface $image): ImageInterface
    {
        $width  = $image->getSize()->getWidth();
        $height = $image->getSize()->getHeight();

        $ratio  = $width/$height;
        if($this->height === null) $this->height = $ratio*($this->width  ?? $width );
        if($this->width  === null) $this->width  = $ratio*($this->height ?? $height);

        return $image->thumbnail(new Box($this->width, $this->height), $this->mode, $this->filter);
    }
}
