<?php

namespace Base\Imagine\Filter\Basic\Definition;

use Base\Imagine\Filter\Basic\ThumbnailFilter;
use Imagine\Image\ImageInterface;

/**
 *
 */
class UltraHighDefinitionFilter extends ThumbnailFilter
{
    /**
     * @return string
     */
    public function __toString()
    {
        return "4k";
    }

    /**
     * @param $mode
     * @param $filter
     */
    public function __construct($mode = ImageInterface::THUMBNAIL_INSET, $filter = ImageInterface::FILTER_UNDEFINED)
    {
        parent::__construct(3840, 2160, $mode, $filter);
    }
}
