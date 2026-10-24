<?php

namespace Base\Repository\Layout;

use Base\Database\Repository\Entities;
use Base\Entity\Layout\ImageCrop;

/**
 * The crops of the stored images (audit "lesringostars face au master plan", step R28; see
 * Base\Database\Repository\Entities): ImageCropRepository answers it with the queries
 * its callers ran by name before.
 *
 * @extends Entities<ImageCrop>
 */
interface ImageCrops extends Entities
{
    /** The crop of this slug of the image stored at $source, through the finder's cache. */
    public function oneBySlugOfImage(string $slug, string $source): ?ImageCrop;

    /** The crop of the image stored at $source whose ratio (width0 / height0) is the closest to $ratio0, through the finder's cache. */
    public function oneClosestToRatio(float|int $ratio0, string $source): ?ImageCrop;

    /** The crop of the image stored at $source the closest to this ratio, then width, then height (fractions of the image's). */
    public function oneClosestToRatioAndSize(float|int $ratio0, float|int $width0, float|int $height0, string $source): ?ImageCrop;
}
