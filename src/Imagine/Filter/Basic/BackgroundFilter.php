<?php

namespace Base\Imagine\Filter\Basic;

use Base\Imagine\FilterInterface;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;

class BackgroundFilter implements FilterInterface
{
    /**
     * @var ImagineInterface
     */
    protected $imagine;

    /** @var array */
    protected $options;
    
    public function __toString() {
        return "bkg:".$this->getRGBA(false).":".$this->getPosition().":".($this->getSize() ? implode("x", $this->getSize()) : "");
    }

    public function __construct(ImagineInterface $imagine, array $options = [])
    {
        $this->imagine = $imagine;
        $this->options = $options;
    }

    public function getRGB($hashtag = true):string { return ($hashtag ? "#" : "").ltrim($this->options['transparency'] ?? "FFF", "#"); }
    public function getAlpha():float { return $this->options['transparency'] ?? null; }
    public function getRGBA($hashtag = true):string { return $this->getRGB($hashtag).hex2rgba($this->options['transparency'] ?? 1, false); }

    public function getPosition():string { return $this->options["position"] ?? "center"; }
    public function getSize():array { return $this->options["size"] ?? null; }
    public function getXY(ImageInterface $image)  :array {

        list($width,$height) = $this->getSize();
        $position = $this->getPosition();
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

        return [$x,$y];
    }

    /**
     * {@inheritdoc}
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        $background = $image->palette()->color($this->getRGB(), $this->getAlpha());
        $topLeft = new Point(0, 0);
        $size = $image->getSize();

        if (isset($this->options['size'])) {

            list($width,$height) = $this->getSize() ?? [null,null];
            list($x,$y) = $this->getXY($image);

            $size = new Box($width ?? $size[0], $height ?? $size[1]);
            $topLeft = new Point($x, $y);
        }

        $canvas = $this->imagine->create($size, $background);

        return $canvas->paste($image, $topLeft);
    }
}
