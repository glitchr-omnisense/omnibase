<?php

namespace Base\Imagine\Filter\Basic;

use Imagine\Filter\Basic\Resize;
use Base\Imagine\FilterInterface;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class ScaleFilter implements FilterInterface
{

    /**
     * @var string
     */
    protected $dimensionKey;

    /**
     * @var string
     */
    protected $ratioKey;

    /**
     * @var bool
     */
    protected $absoluteRatio;

    public function __toString()
    {
        $scale = $this->options[$this->ratioKey] ?? implode("x", $this->options[$this->dimensionKey] ?? []);
        return "scale:".$scale;
    }

    public function __construct(array $options = [], $dimensionKey = 'dim', $ratioKey = 'to', $absoluteRatio = true)
    {
        $this->options = $options;
        $this->dimensionKey = $dimensionKey;
        $this->ratioKey = $ratioKey;
        $this->absoluteRatio = $absoluteRatio;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        if (!isset($this->options[$this->dimensionKey]) && !isset($this->options[$this->ratioKey])) {
            throw new \InvalidArgumentException("Missing $this->dimensionKey or $this->ratioKey option.");
        }

        $size = $image->getSize();
        $origWidth = $size->getWidth();
        $origHeight = $size->getHeight();
        $ratio = 1;

        if (isset($this->options[$this->ratioKey])) {
            $ratio = $this->absoluteRatio ? $this->options[$this->ratioKey] : $this->calcAbsoluteRatio($this->options[$this->ratioKey]);
        } elseif (isset($this->options[$this->dimensionKey])) {
            $size = $this->options[$this->dimensionKey];
            $width = isset($size[0]) ? $size[0] : null;
            $height = isset($size[1]) ? $size[1] : null;

            $widthRatio = $width / $origWidth;
            $heightRatio = $height / $origHeight;

            if (null === $width || null === $height) {
                $ratio = max($widthRatio, $heightRatio);
            } else {
                $ratio = ('min' === $this->dimensionKey) ? max($widthRatio, $heightRatio) : min($widthRatio, $heightRatio);
            }
        }

        if ($this->isImageProcessable($ratio)) {
            $filter = new Resize(new Box(round($origWidth * $ratio), round($origHeight * $ratio)));

            return $filter->apply($image);
        }

        return $image;
    }

    protected function calcAbsoluteRatio($ratio)
    {
        return $ratio;
    }

    protected function isImageProcessable($ratio)
    {
        return true;
    }
}
