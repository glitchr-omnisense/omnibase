<?php

namespace Base\Filter\Advanced;

class UpscaleFilter extends ScaleFilter
{
    public function __toString() { return "dwn".parent::__toString(); }
    public function __construct()
    {
        parent::__construct([], 'min', 'by', false);
    }

    protected function calcAbsoluteRatio($ratio)
    {
        return 1 + $ratio;
    }

    protected function isImageProcessable($ratio)
    {
        return $ratio > 1;
    }
}
