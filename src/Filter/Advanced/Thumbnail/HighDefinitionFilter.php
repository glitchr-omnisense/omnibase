<?php

namespace Base\Filter\Advanced\Thumbnail;

use Base\Filter\Advanced\ThumbnailFilter;
use Imagine\Image\ImageInterface;

class HighDefinitionFilter extends ThumbnailFilter
{
    public function __construct($mode = ImageInterface::THUMBNAIL_INSET, $filter = ImageInterface::FILTER_UNDEFINED)
    {
        parent::__construct(1280, 720, $mode, $filter);
    }
}
