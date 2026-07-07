<?php

namespace Tests\Base\Service;

use Base\Service\ColorExtractor;
use Base\Service\Model\Color\Palette;
use PHPUnit\Framework\TestCase;

class ColorExtractorTest extends TestCase
{
    private const RED = 0xFF0000;
    private const BLUE = 0x0000FF;

    private function makeExtractor(): ColorExtractor
    {
        // 4 red pixels, 2 blue pixels.
        $image = imagecreatetruecolor(3, 2);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
        imagesetpixel($image, 0, 0, imagecolorallocate($image, 0, 0, 255));
        imagesetpixel($image, 1, 0, imagecolorallocate($image, 0, 0, 255));

        return new ColorExtractor(Palette::fromGD($image));
    }

    /**
     * The extractor does NOT rank by pixel count alone: colors are weighted
     * by chroma x darkness x sqrt(count) in Lab space. Blue is darker and
     * more chromatic than red, so it outranks red despite 4 red pixels vs 2.
     */
    public function testExtractReturnsTheMostRepresentativeColor(): void
    {
        $this->assertSame([self::BLUE], $this->makeExtractor()->extract());
    }

    public function testExtractSeveralColorsKeepsRepresentativityOrder(): void
    {
        $this->assertSame([self::BLUE, self::RED], $this->makeExtractor()->extract(2));
    }

    public function testExtractIsCappedByThePaletteSize(): void
    {
        $extractor = $this->makeExtractor();

        $colors = $extractor->extract(10);

        $this->assertCount(2, $colors);
        $this->assertEqualsCanonicalizing([self::BLUE, self::RED], $colors);
    }

    public function testSingleColorImage(): void
    {
        $image = imagecreatetruecolor(2, 2);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));

        $extractor = new ColorExtractor(Palette::fromGD($image));

        $this->assertSame([self::RED], $extractor->extract());
        $this->assertSame([self::RED], $extractor->extract(5));
    }
}
