<?php

namespace Base\Entity\Extension;

use Base\Annotations\Annotation\Hashify;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Extension\Abstract\AbstractExtension;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Extension\RevisionRepository;

use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=RevisionRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry(value="revision")
 */
class Revision extends AbstractExtension
{
    public static function __iconizeStatic(): ?array
    {
        return ["fas fa-sort-numeric-down"];
    }

    /**
     * @ORM\Column(type="integer")
     * @Hashify(random=true)
     */
    protected $hash;
    public function getHash()
    {
        return $this->hash;
    }
    public function getHashShort()
    {
        return substr($this->hash, 0, 7);
    }

    public function supports(): bool
    {
        return true;
    }
}
