<?php

namespace Base\Contract;

/**
 * An image's address at the size a page shows it (plan "omnibase agnostique",
 * C5): what omnibase's `|picture` filter answers - a thumbnail of a stored
 * image, an address or a public path as it is, an asset of the build. Null for
 * no image.
 *
 * The Fondation's own contract, reached by Runtime::pictures() or given to a
 * screen's builder. Under Symfony, Base\Bridge\Symfony\MediaPictures: the
 * filter's own answers (MediaService's thumbnails).
 */
interface Pictures
{
    public function picture(?string $image, ?int $width = null, ?int $height = null): ?string;
}
