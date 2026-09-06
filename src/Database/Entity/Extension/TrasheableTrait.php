<?php

namespace Base\Database\Entity\Extension;

use Base\Database\Attribute\Timestamp;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

trait TrasheableTrait
{
    #[ORM\Column(type:"datetime", nullable:"true")]
    #[Timestamp(on:"remove")]
    protected $deletedAt;

    /**
     * @return bool
     */
    public function isDeleted()
    {
        return null !== $this->deletedAt;
    }

    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * Take the entity back out of the trash. Nullable is the whole point of
     * the widened setter above: without it there was no way, in code, to undo
     * a soft deletion.
     */
    public function restore(): self
    {
        $this->deletedAt = null;
        return $this;
    }
}
