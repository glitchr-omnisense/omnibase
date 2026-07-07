<?php

namespace Tests\Base\Service\Model\Color;

use Base\Service\Model\Color\Palette;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Palette is adapted from league/color-extractor but had lost its static
 * factories (leaving a protected constructor nothing could reach) and still
 * checked is_resource(), which no GD image passes since PHP 8 turned them
 * into \GdImage objects. Both are fixed; this locks the class in as usable.
 */
class PaletteTest extends TestCase
{
    private const RED = 0xFF0000;
    private const BLUE = 0x0000FF;
    private const GREEN = 0x00FF00;

    /**
     * @return \GdImage 3x2 truecolor image: 4 red pixels, 2 blue pixels
     */
    private function makeRedBlueImage(): \GdImage
    {
        $image = imagecreatetruecolor(3, 2);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
        imagesetpixel($image, 0, 0, imagecolorallocate($image, 0, 0, 255));
        imagesetpixel($image, 1, 0, imagecolorallocate($image, 0, 0, 255));

        return $image;
    }

    public function testColorsAreCountedAndSortedByOccurrence(): void
    {
        $palette = Palette::fromGD($this->makeRedBlueImage());

        $this->assertCount(2, $palette);
        $this->assertSame(4, $palette->getCount(self::RED));
        $this->assertSame(2, $palette->getCount(self::BLUE));
        $this->assertSame(
            [self::RED => 4, self::BLUE => 2],
            iterator_to_array($palette->getIterator())
        );
    }

    public function testGetDominantColors(): void
    {
        $palette = Palette::fromGD($this->makeRedBlueImage());

        $this->assertSame([self::RED => 4], $palette->getDominantColors(1));
        $this->assertSame([self::RED => 4, self::BLUE => 2], $palette->getDominantColors());
    }

    public function testFromFilename(): void
    {
        $filename = tempnam(sys_get_temp_dir(), 'palette') . '.png';
        imagepng($this->makeRedBlueImage(), $filename);

        try {
            $palette = Palette::fromFilename($filename);

            $this->assertSame(4, $palette->getCount(self::RED));
            $this->assertNull($palette->getColorKey());
        } finally {
            unlink($filename);
        }
    }

    public function testFullyTransparentPixelsAreSkippedWithoutAColorKey(): void
    {
        $image = imagecreatetruecolor(2, 1);
        imagealphablending($image, false);
        imagesetpixel($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
        imagesetpixel($image, 1, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        $palette = Palette::fromGD($image);

        $this->assertSame([self::RED => 1], iterator_to_array($palette->getIterator()));
    }

    public function testColorKeyReplacesFullyTransparentPixels(): void
    {
        $image = imagecreatetruecolor(2, 1);
        imagealphablending($image, false);
        imagesetpixel($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
        imagesetpixel($image, 1, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        $palette = Palette::fromGD($image, self::GREEN);

        $this->assertSame(self::GREEN, $palette->getColorKey());
        $this->assertSame(
            [self::RED => 1, self::GREEN => 1],
            iterator_to_array($palette->getIterator())
        );
    }

    public function testIndexedImagesAreConvertedPixelByPixel(): void
    {
        $image = imagecreate(2, 1); // palette-based, not truecolor
        imagecolorallocate($image, 255, 0, 0); // first allocation fills the background
        imagesetpixel($image, 1, 0, imagecolorallocate($image, 0, 0, 255));

        $palette = Palette::fromGD($image);

        $this->assertSame(
            [self::RED => 1, self::BLUE => 1],
            iterator_to_array($palette->getIterator())
        );
    }

    public function testLoadResourceRejectsNonImages(): void
    {
        $palette = Palette::fromGD($this->makeRedBlueImage());

        $this->expectException(InvalidArgumentException::class);
        $palette->loadResource(false);
    }

    public function testOutOfRangeColorKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Palette::fromGD($this->makeRedBlueImage(), 16777216);
    }

    public function testNegativeColorKeyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Palette::fromGD($this->makeRedBlueImage(), -1);
    }
}
