<?php

namespace Base\Filter\Advanced;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;

class PasteFilterLoader implements FilterInterface
{
    /**
     * @var ImagineInterface
     */
    protected $imagine;

    /**
     * @var string
     */
    protected $projectDir;

    public function __construct(ImageInterface $destination, int $x = 0, int $y = 0)
    {
        $this->destination = $destination;
        $this->x = $x ?? 0;
        $this->y = $y ?? 0;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->paste($this->destination, new Point($this->x, $this->y)
        );
    }
}
