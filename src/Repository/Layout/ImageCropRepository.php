<?php

namespace Base\Repository\Layout;

use Base\Entity\Layout\ImageCrop;
use Base\Database\Repository\ServiceEntityRepository;

use Base\Repository\Layout\ImageCrops;

/**
 * @method ImageCrop|null find($id)
 * @method ImageCrop|null findOneBy(array $criteria, array $orderBy = null)
 * @method ImageCrop[]    findAll()
 * @method ImageCrop[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImageCropRepository extends ServiceEntityRepository implements ImageCrops
{
    /** ImageCrops (step R28): ImageRoute's finder. */
    public function oneBySlugOfImage(string $slug, string $source): ?ImageCrop
    {
        return $this->cacheOneBySlug($slug, ["image.source" => $source]);
    }

    public function oneClosestToRatio(float|int $ratio0, string $source): ?ImageCrop
    {
        // The criteria, the order, the offset, the grouping, then the selected ratio (it was given as the offset).
        return $this->cacheOneByRatio0ClosestTo($ratio0, ["image.source" => $source], [], null, [], ["ratio0" => "e.width0/e.height0"])[0] ?? null;
    }

    public function oneClosestToRatioAndSize(float|int $ratio0, float|int $width0, float|int $height0, string $source): ?ImageCrop
    {
        return $this->findOneByRatio0ClosestToAndWidth0ClosestToAndHeight0ClosestTo($ratio0, $width0, $height0, ["image.source" => $source], [], null, [], ["ratio0" => "e.width0/e.height0"])[0] ?? null;
    }
}
