<?php

namespace Base\Imagine\Filter\Basic;

use Base\Imagine\FilterInterface;
use Imagine\Image\ImageInterface;

class RotateFilter implements FilterInterface
{
    /** * @var int */
    protected int $angle;

    public function __toString() { return mod($this->angle, 360) ? "rot:".$this->angle : ""; }
    public function __construct(int $angle = 0)
    {
        $this->angle = $angle;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        return 0 === $this->angle ? $image : $image->rotate($this->angle);
    }
}
