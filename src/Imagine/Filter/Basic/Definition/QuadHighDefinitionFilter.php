<?php

namespace Base\Imagine\Filter\Basic\Definition;

use Base\Imagine\Filter\Basic\ThumbnailFilter;
use Imagine\Image\ImageInterface;

/**
 *
 */
class QuadHighDefinitionFilter extends ThumbnailFilter
{
    /**
     * @return string
     */
    public function __toString()
    {
        return "qhd";
    }

    /**
     * @param $mode
     * @param $filter
     */
    public function __construct($mode = ImageInterface::THUMBNAIL_INSET, $filter = ImageInterface::FILTER_UNDEFINED)
    {
        parent::__construct(2560, 1440, $mode, $filter);
    }
}
