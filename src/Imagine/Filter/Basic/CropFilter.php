<?php

namespace Base\Imagine\Filter\Basic;

use Base\Imagine\FilterInterface;
use Exception;
use Imagine\Filter\Basic\Crop;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;

class CropFilter implements FilterInterface
{
    public function __toString() { return "crop:".$this->getPosition().":".implode("x",$this->getXY()).":".implode("x",$this->getSize()); }

    public function __construct(int $x = 0, int $y = 0, ?int $width = null, ?int $height = null, string $position = "lefttop")
    {
        $this->x = $x ?? 0;
        $this->y = $y ?? 0;

        $this->width  = $width  ?? 0;
        $this->height = $height ?? 0;
    }

    public function getPosition(): string { return $this->position ?? "topleft"; }
    public function getSize(): array { return [$this->width, $this->height]; }
    public function getXY()  : array { return [$this->x, $this->y]; }
    public function getXYOffset(ImageInterface $image)  :array {

        list($width,$height) = $this->getSize();
        $position = $this->getPosition();
        switch ($position) {

            case 'topleft':
                $x0 = 0;
                $y0 = 0;
                break;
            case 'top':
                $x0 = ($width - $image->getSize()->getWidth()) / 2;
                $y0 = 0;
                break;
            case 'topright':
                $x0 = $width - $image->getSize()->getWidth();
                $y0 = 0;
                break;
            case 'left':
                $x0 = 0;
                $y0 = ($height - $image->getSize()->getHeight()) / 2;
                break;
            case 'centerright':
                $x0 = $width - $image->getSize()->getWidth();
                $y0 = ($height - $image->getSize()->getHeight()) / 2;
                break;
            case 'center':
                $x0 = ($width - $image->getSize()->getWidth()) / 2;
                $y0 = ($height - $image->getSize()->getHeight()) / 2;
                break;
            case 'centerleft':
                $x0 = 0;
                $y0 = ($height - $image->getSize()->getHeight()) / 2;
                break;
            case 'right':
                $x0 = $width - $image->getSize()->getWidth();
                $y0 = ($height - $image->getSize()->getHeight()) / 2;
                break;
            case 'bottomleft':
                $x0 = 0;
                $y0 = $height - $image->getSize()->getHeight();
                break;
            case 'bottom':
                $x0 = ($width - $image->getSize()->getWidth()) / 2;
                $y0 = $height - $image->getSize()->getHeight();
                break;
            case 'bottomright':
                $x0 = $width - $image->getSize()->getWidth();
                $y0 = $height - $image->getSize()->getHeight();
                break;
            default:
                throw new \InvalidArgumentException("Unexpected position '{$position}'");
                break;
        }

        return [$x0, $y0];
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        list($x0,$y0) = $this->getXYOffset($image);

        list($x,$y)   = $this->getXY();
        list($w,$h)   = $this->getSize();

        $filter = new Crop(
            new Point(          $x - $x0,            $y - $y0),
            new Box  ($this->width - $x0, $this->height - $y0)
        );

        try { return $filter->apply($image); }
        catch (Exception $e) { return $image; }
    }
}
