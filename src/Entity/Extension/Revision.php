<?php

namespace Base\Entity\Extension;

use Base\Annotations\Annotation\Hashify;
use Base\Database\Traits\EntityExtensionTrait;
use Base\Model\IconizeInterface;
use Base\Traits\BaseTrait;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Extension\RevisionRepository;

/**
 * @ORM\Entity(repositoryClass=RevisionRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class Revision implements IconizeInterface
{
    use BaseTrait;
    use EntityExtensionTrait;

    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-sort-numeric-down"]; } 

    /**
     * @ORM\Column(type="integer")
     * @Hashify(random=true)
     */
    protected $hash;
    public function getHash() { return $this->hash; }
    public function getHashShort() { return substr($this->hash, 0, 7); }
}
