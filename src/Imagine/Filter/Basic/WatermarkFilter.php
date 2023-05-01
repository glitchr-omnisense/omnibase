<?php

namespace Base\Imagine\Filter\Basic;

use Base\Imagine\FilterInterface;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;
use InvalidArgumentException;

/**
 *
 */
class WatermarkFilter implements FilterInterface
{
    /**
     * @var ImagineInterface
     */
    protected $imagine;
    private \Imagine\Image\BoxInterface $watermarkSize;
    private array $options;
    private ImageInterface $watermark;

    /**
     * @return string
     */
    public function __toString()
    {
        $md5sum = md5(serialize($this->watermark) . serialize($this->options));
        return mod($this->angle, 360) ? "wmk:" . $md5sum : "";
    }

    public function __construct(ImagineInterface $imagine, ImageInterface $watermark, array $options = [])
    {
        $this->imagine = $imagine;

        $this->watermark = $watermark;
        $this->options = $options;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        $this->options += [
            'size' => null,
            'position' => 'center',
        ];

        if ($this->options['size'] === null || str_ends_with($this->options['size'], '%')) {
            $this->options['size'] = substr($this->options['size'], 0, -1) / 100;
        }

        $size = $image->getSize();
        $this->watermarkSize = $this->watermark->getSize();

        // If 'null': Downscale if needed
        if (!$this->options['size'] && ($size->getWidth() < $this->watermarkSize->getWidth() || $size->getHeight() < $this->watermarkSize->getHeight())) {
            $this->options['size'] = 1.0;
        }

        if ($this->options['size']) {
            $factor = $this->options['size'] * min($size->getWidth() / $this->watermarkSize->getWidth(), $size->getHeight() / $this->watermarkSize->getHeight());

            $this->watermark->resize(new Box($this->watermarkSize->getWidth() * $factor, $this->watermarkSize->getHeight() * $factor));
            $this->watermarkSize = $this->watermark->getSize();
        }

        if ('multiple' === $this->options['position']) {
            // we loop over the coordinates of the image to apply the watermark as much as possible
            $pasteX = 0;
            while ($pasteX < $size->getWidth()) {
                $pasteY = 0;
                while ($pasteY < $size->getHeight()) {
                    $image->paste($this->watermark, new Point($pasteX, $pasteY));
                    $pasteY += $this->watermarkSize->getHeight();
                }
                $pasteX += $this->watermarkSize->getWidth();
            }

            return $image;
        }

        switch ($this->options['position']) {
            case 'topleft':
                $x = 0;
                $y = 0;
                break;
            case 'top':
                $x = ($size->getWidth() - $this->watermarkSize->getWidth()) / 2;
                $y = 0;
                break;
            case 'topright':
                $x = $size->getWidth() - $this->watermarkSize->getWidth();
                $y = 0;
                break;
            case 'left':
                $x = 0;
                $y = ($size->getHeight() - $this->watermarkSize->getHeight()) / 2;
                break;
            case 'center':
                $x = ($size->getWidth() - $this->watermarkSize->getWidth()) / 2;
                $y = ($size->getHeight() - $this->watermarkSize->getHeight()) / 2;
                break;
            case 'right':
                $x = $size->getWidth() - $this->watermarkSize->getWidth();
                $y = ($size->getHeight() - $this->watermarkSize->getHeight()) / 2;
                break;
            case 'bottomleft':
                $x = 0;
                $y = $size->getHeight() - $this->watermarkSize->getHeight();
                break;
            case 'bottom':
                $x = ($size->getWidth() - $this->watermarkSize->getWidth()) / 2;
                $y = $size->getHeight() - $this->watermarkSize->getHeight();
                break;
            case 'bottomright':
                $x = $size->getWidth() - $this->watermarkSize->getWidth();
                $y = $size->getHeight() - $this->watermarkSize->getHeight();
                break;
            default:
                throw new InvalidArgumentException("Unexpected position '{$this->options['position']}'");
                break;
        }

        return $image->paste($this->watermark, new Point($x, $y));
    }
}
