<?php

namespace Base\Imagine\Filter\Basic;

class DownscaleFilter extends ScaleFilter
{
    public function __toString()
    {
        return "dwn".parent::__toString();
    }
    public function __construct()
    {
        parent::__construct([], 'max', 'by', false);
    }

    protected function calcAbsoluteRatio($ratio)
    {
        return 1 - ($ratio > 1 ? $ratio - floor($ratio) : $ratio);
    }

    protected function isImageProcessable($ratio)
    {
        return $ratio < 1;
    }
}
