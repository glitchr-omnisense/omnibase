<?php

namespace Base\Bridge\Symfony;

use Base\Contract\Pictures;
use Base\Twig\Extension\CommonsTwigExtension;

/**
 * The Pictures contract under Symfony (plan "omnibase agnostique", C5): the
 * answers of omnibase's `|picture` filter itself - a stored image's thumbnail
 * by MediaService, an address or a public path as it is, an asset of the
 * build - so a screen's images are those the templates printed.
 */
final class MediaPictures implements Pictures
{
    public function __construct(private readonly CommonsTwigExtension $commons)
    {
    }

    public function picture(?string $image, ?int $width = null, ?int $height = null): ?string
    {
        return $this->commons->picture($image, $width, $height);
    }
}
