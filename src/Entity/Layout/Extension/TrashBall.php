<?php

namespace Base\Entity\Extension;

use Base\Annotations\Annotation\Timestamp;
use Base\Database\Traits\EntityExtensionTrait;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Extension\OrderingRepository;

/**
 * @ORM\Entity(repositoryClass=TrashBallRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class TrashBall implements IconizeInterface
{
    use EntityExtensionTrait;

    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-trash-alt"]; } 

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $permanentAfter;
    public function getPermanentAfter(): ?\DateTimeInterface { return $this->permanentAfter; }
    public function setPermanentAfter(\DateTimeInterface $permanentAfter): self
    {
        $this->permanentAfter = $permanentAfter;
        return $this;
    }
}
