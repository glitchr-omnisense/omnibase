<?php

namespace Base\Filter\Basic;

use Base\Filter\FilterInterface;
use Imagine\Filter\Basic\Crop;
use Imagine\Filter\Basic\Resize;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FixedFilter implements FilterInterface
{
    public function __toString() { return ($this->options["width"] ?? "auto")."x".($this->options["height"] ?? "auto"); }
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setRequired(['width', 'height']);
        $options = $optionsResolver->resolve($this->options);

        // get the original image size and create a crop box
        $size = $image->getSize();
        $box = new Box($options['width'], $options['height']);

        // determine scale
        if ($size->getWidth() / $size->getHeight() > $box->getWidth() / $box->getHeight()) {
            $size = $size->heighten($box->getHeight());
        } else {
            $size = $size->widen($box->getWidth());
        }

        // define filters
        $resize = new Resize($size);
        $origin = new Point(
            floor(($size->getWidth() - $box->getWidth()) / 2),
            floor(($size->getHeight() - $box->getHeight()) / 2)
        );
        $crop = new Crop($origin, $box);

        // apply filters to image
        $image = $resize->apply($image);
        $image = $crop->apply($image);

        return $image;
    }
}
