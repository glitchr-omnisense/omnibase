<?php

namespace Base\Database\Traits;

use Base\Annotations\Annotation\Timestamp;
use Doctrine\ORM\Mapping as ORM;

trait TrasheableTrait
{
    /**
     * @ORM\Column(type="datetime", nullable="true")
     * @Timestamp(on="remove")
     */
    protected $deletedAt;
    public function isDeleted() { return null !== $this->deletedAt; }
    public function getDeletedAt(): ?\DateTimeInterface { return $this->deletedAt; }
    public function setDeletedAt(\DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }
}