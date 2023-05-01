<?php

namespace Base\Service\Model\Color;

use ArrayIterator;
use Countable;
use GdImage;
use InvalidArgumentException;
use IteratorAggregate;

/**
 *
 */
class Palette implements Countable, IteratorAggregate
{
    protected array $colors = [];

    protected ?int $colorKey = null;

    /**
     * @return int|null
     */
    public function getColorKey()
    {
        return $this->colorKey;
    }

    /**
     * @param int|null $colorKey
     * @return $this
     */
    /**
     * @param int|null $colorKey
     * @return $this
     */
    public function setColorKey(int|null $colorKey)
    {
        $this->colorKey = $colorKey;
        return $this;
    }

    public function count(): int
    {
        return count($this->colors);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->colors);
    }

    public function getCount(int $color): int
    {
        return $this->colors[$color];
    }

    public function getDominantColors(int $limit = null): array
    {
        return array_slice($this->colors, 0, $limit, true);
    }

    /**
     * @param $filename
     * @param int|null $colorKey
     */
    protected function __construct($filename, int|null $colorKey = null)
    {
        $this->load($filename, $colorKey);
    }

    /**
     * @param $filenameOrImage
     * @param int|null $colorKey
     * @return $this
     */
    /**
     * @param $filenameOrImage
     * @param int|null $colorKey
     * @return $this
     */
    public function load($filenameOrImage, int|null $colorKey = null): Palette
    {
        if ($filenameOrImage instanceof GdImage) {
            $image = $filenameOrImage;
        } else {
            $image = imagecreatefromstring(file_get_contents($filenameOrImage));
        }

        $this->loadResource($image, $colorKey);
        if (!$filenameOrImage instanceof GdImage) {
            imagedestroy($image);
        }

        return $this;
    }

    public function loadResource(GdImage|false $resource, int|null $colorKey = null): Palette
    {
        if (!is_resource($resource) || get_resource_type($resource) != 'gd') {
            throw new InvalidArgumentException('Image must be a gd resource');
        }

        if ($colorKey !== null && (!is_numeric($colorKey) || $colorKey < 0 || $colorKey > 16777215)) {
            throw new InvalidArgumentException(sprintf('"%s" does not represent a valid color', $colorKey));
        }

        $areColorsIndexed = !imageistruecolor($resource);
        $width = imagesx($resource);
        $height = imagesy($resource);

        $this->colors = [];
        $this->colorKey = $colorKey;

        $colorKeyRed = ($colorKey >> 16) & 0xFF;
        $colorKeyGreen = ($colorKey >> 8) & 0xFF;
        $colorKeyBlue = ($colorKey) & 0xFF;

        for ($x = 0; $x < $width; ++$x) {
            for ($y = 0; $y < $height; ++$y) {
                $color = imagecolorat($resource, $x, $y);
                if ($areColorsIndexed) {
                    $colorComponents = imagecolorsforindex($resource, $color);
                    $color = ($colorComponents['alpha'] * 16777216) +
                        ($colorComponents['red'] * 65536) +
                        ($colorComponents['green'] * 256) +
                        ($colorComponents['blue']);
                }

                if ($alpha = $color >> 24) {
                    if ($colorKey === null) {
                        continue;
                    }

                    $alpha /= 127;
                    $color = (int)(($color >> 16 & 0xFF) * (1 - $alpha) + $colorKeyRed * $alpha) * 65536 +
                        (int)(($color >> 8 & 0xFF) * (1 - $alpha) + $colorKeyGreen * $alpha) * 256 +
                        (int)(($color & 0xFF) * (1 - $alpha) + $colorKeyBlue * $alpha);
                }

                isset($this->colors[$color]) ?
                    $this->colors[$color] += 1 :
                    $this->colors[$color] = 1;
            }
        }

        arsort($this->colors);

        return $this;
    }
}
