<?php

namespace Base\Entity\Extension;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Extension\Abstract\AbstractExtension;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Extension\OrderingRepository;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=OrderingRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry(value="ordering")
 */
class Ordering extends AbstractExtension
{
    public static function __iconizeStatic(): ?array
    {
        return ["fas fa-sort-alpha-down"];
    }

    public function supports(): bool
    {
        $needsOrdering = array_filter($this->getEntityData(), fn ($v) => !is_identity($v));
        return $needsOrdering !== [];
    }
}
