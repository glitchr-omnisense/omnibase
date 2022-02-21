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
     * @ORM\Column(type="datetime")
     * @Timestamp(on="create")
     */
    protected $deletedAt;
    public function getDeletedAt(): ?\DateTimeInterface { return $this->deletedAt; }
    public function setDeletedAt(\DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }
}
