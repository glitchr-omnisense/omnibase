<?php

namespace Base\Imagine\Filter\Basic;

/**
 *
 */
class UpscaleFilter extends ScaleFilter
{
    /**
     * @return string
     */
    public function __toString()
    {
        return "dwn" . parent::__toString();
    }

    public function __construct()
    {
        parent::__construct([], 'min', 'by', false);
    }

    /**
     * @param $ratio
     * @return int
     */
    protected function calcAbsoluteRatio($ratio)
    {
        return 1 + $ratio;
    }

    /**
     * @param $ratio
     * @return bool
     */
    protected function isImageProcessable($ratio)
    {
        return $ratio > 1;
    }
}
