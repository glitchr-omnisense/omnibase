<?php

namespace Base\Imagine\Filter\Basic;

use Base\Imagine\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;

/**
 *
 */
class PasteFilter implements FilterInterface
{
    /**
     * @var ImagineInterface
     */
    protected ImagineInterface $imagine;
    private int $y;
    private int $x;
    private ImageInterface $destination;

    /**
     * @return string
     */
    public function __toString()
    {
        return "paste:" . $this->x . "x" . $this->y;
    }

    public function __construct(ImageInterface $destination, int $x = 0, int $y = 0)
    {
        $this->destination = $destination;
        $this->x = $x ?? 0;
        $this->y = $y ?? 0;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        return $image->paste($this->destination, new Point($this->x, $this->y));
    }
}
