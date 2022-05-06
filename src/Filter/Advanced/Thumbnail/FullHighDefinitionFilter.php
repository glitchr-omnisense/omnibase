<?php

namespace Base\Filter\Advanced\Thumbnail;

use Base\Filter\Advanced\ThumbnailFilter;
use Imagine\Image\ImageInterface;

class FullHighDefinitionFilter extends ThumbnailFilter
{
    public function __toString() { return "fhd"; }
    public function __construct($mode = ImageInterface::THUMBNAIL_INSET, $filter = ImageInterface::FILTER_UNDEFINED)
    {
        parent::__construct(1920, 1080, $mode, $filter);
    }
}
