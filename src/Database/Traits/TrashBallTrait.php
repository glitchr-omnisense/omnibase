<?php

namespace Base\Database\Traits;

use Base\Annotations\Annotation\Timestamp;
use Base\Traits\BaseTrait;
use Doctrine\ORM\Mapping as ORM;

trait EntityExtensionTrait
{
    use BaseTrait;

    /**
     * @ORM\Column(type="datetime")
     * @Timestamp(on="remove")
     */
    protected $deletedAt;
    public function getDeletedAt(): ?\DateTimeInterface { return $this->deletedAt; }
    public function setDeletedAt(\DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }
}