<?php

namespace Base\Filter\Advanced\Thumbnail;

use Base\Filter\Advanced\ThumbnailFilter;
use Imagine\Image\ImageInterface;

class QuadHighDefinitionFilter extends ThumbnailFilter
{
    public function __construct($mode = ImageInterface::THUMBNAIL_INSET, $filter = ImageInterface::FILTER_UNDEFINED)
    {
        parent::__construct(2560, 1440, $mode, $filter);
    }
}
