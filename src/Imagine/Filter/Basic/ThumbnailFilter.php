<?php

namespace Base\Imagine\Filter\Basic;

use Base\Imagine\FilterInterface;
use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;

class ThumbnailFilter implements FilterInterface
{
    /** * @var int */
    protected ?int $width;
    /** * @var int */
    protected ?int $height;
    /** * @var int */
    protected $mode;
    /** * @var string */
    protected $filter;

    public function __construct(?int $width = null, ?int $height = null, $mode = ImageInterface::THUMBNAIL_INSET, $filter = ImageInterface::FILTER_UNDEFINED)
    {
        $this->width  = $width;
        $this->height = $height;
        $this->mode   = $mode;
        $this->filter = $filter;
    }

    public function __toString()
    {
        return ($this->width ?? "auto")."x".($this->height ?? "auto");
    }
    public function apply(ImageInterface $image): ImageInterface
    {
        $width  = $image->getSize()->getWidth();
        $height = $image->getSize()->getHeight();

        $ratio  = $width/$height;
        if ($this->height === null) {
            $this->height = $ratio*($this->width  ?? $width);
        }
        if ($this->width  === null) {
            $this->width  = $ratio*($this->height ?? $height);
        }

        return $image->thumbnail(new Box($this->width, $this->height), $this->mode, $this->filter);
    }

    public function getWidth()
    {
        return $this->width;
    }
    public function getHeight()
    {
        return $this->height;
    }
    public function getMode()
    {
        return $this->mode;
    }

    public function resize(BoxInterface $imageSize)
    {
        $mode = $this->mode & 0xffff;

        $allowUpscale = (bool) ($mode & ImageInterface::THUMBNAIL_FLAG_UPSCALE);
        $size = new Box($this->width, $this->height);

        if ($size->getWidth() === $imageSize->getWidth() && $size->getHeight() === $imageSize->getHeight()) {
            // The thumbnail size is the same as the wanted size.
            return $size;
        }
        if (!$allowUpscale && $size->contains($imageSize)) {
            // Thumbnail is smaller than the image and we are not upscaling
            return $size;
        }

        $ratios = array(
            $size->getWidth() / $imageSize->getWidth(),
            $size->getHeight() / $imageSize->getHeight(),
        );

        switch ($mode) {
            case ImageInterface::THUMBNAIL_OUTBOUND:
                // Crop the image so that it fits the wanted size
                $ratio = max($ratios);
                if ($imageSize->contains($size)) {
                    // Downscale the image
                    return $size;
                }

                if ($allowUpscale) {
                    // Upscale the image so that the max dimension will be the wanted one
                    $imageSize = $imageSize->scale($ratio);
                }

                return new Box(
                    min($imageSize->getWidth(), $size->getWidth()),
                    min($imageSize->getHeight(), $size->getHeight())
                );

            case ImageInterface::THUMBNAIL_INSET:
            default:

                $ratio = min($ratios);
                return $imageSize->scale($ratio);
        }
    }
}
