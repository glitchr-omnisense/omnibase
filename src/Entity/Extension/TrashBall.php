<?php

namespace Base\Entity\Extension;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Extension\Abstract\AbstractExtension;

use Base\Repository\Extension\TrashBallRepository;
use Doctrine\ORM\Mapping as ORM;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=TrashBallRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry(value="trash_ball")
 */
class TrashBall extends AbstractExtension
{
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-trash-alt"];
    }

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $permanentAfter;

    public function getPermanentAfter(): ?\DateTimeInterface
    {
        return $this->permanentAfter;
    }

    public function setPermanentAfter(\DateTimeInterface $permanentAfter): self
    {
        $this->permanentAfter = $permanentAfter;
        return $this;
    }

    public function supports(): bool
    {
        return true;
    }
}
