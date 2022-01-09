<?php

namespace Base\Filter\Advanced;

use Imagine\Filter\FilterInterface;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;

class BackgroundFilterLoader implements FilterInterface
{
    /**
     * @var ImagineInterface
     */
    protected $imagine;

    public function __construct(ImagineInterface $imagine, array $options = [])
    {
        $this->imagine = $imagine;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(ImageInterface $image)
    {
        $background = $image->palette()->color(
            isset($this->options['color']) ? $this->options['color'] : '#fff',
            isset($this->options['transparency']) ? $this->options['transparency'] : null
        );
        $topLeft = new Point(0, 0);
        $size = $image->getSize();

        if (isset($this->options['size'])) {
            $width = isset($this->options['size'][0]) ? $this->options['size'][0] : null;
            $height = isset($this->options['size'][1]) ? $this->options['size'][1] : null;

            $position = isset($this->options['position']) ? $this->options['position'] : 'center';
            switch ($position) {
                case 'topleft':
                    $x = 0;
                    $y = 0;
                    break;
                case 'top':
                    $x = ($width - $image->getSize()->getWidth()) / 2;
                    $y = 0;
                    break;
                case 'topright':
                    $x = $width - $image->getSize()->getWidth();
                    $y = 0;
                    break;
                case 'left':
                    $x = 0;
                    $y = ($height - $image->getSize()->getHeight()) / 2;
                    break;
                case 'centerright':
                    $x = $width - $image->getSize()->getWidth();
                    $y = ($height - $image->getSize()->getHeight()) / 2;
                    break;
                case 'center':
                    $x = ($width - $image->getSize()->getWidth()) / 2;
                    $y = ($height - $image->getSize()->getHeight()) / 2;
                    break;
                case 'centerleft':
                    $x = 0;
                    $y = ($height - $image->getSize()->getHeight()) / 2;
                    break;
                case 'right':
                    $x = $width - $image->getSize()->getWidth();
                    $y = ($height - $image->getSize()->getHeight()) / 2;
                    break;
                case 'bottomleft':
                    $x = 0;
                    $y = $height - $image->getSize()->getHeight();
                    break;
                case 'bottom':
                    $x = ($width - $image->getSize()->getWidth()) / 2;
                    $y = $height - $image->getSize()->getHeight();
                    break;
                case 'bottomright':
                    $x = $width - $image->getSize()->getWidth();
                    $y = $height - $image->getSize()->getHeight();
                    break;
                default:
                    throw new \InvalidArgumentException("Unexpected position '{$position}'");
                    break;
            }

            $size = new Box($width, $height);
            $topLeft = new Point($x, $y);
        }

        $canvas = $this->imagine->create($size, $background);

        return $canvas->paste($image, $topLeft);
    }
}
