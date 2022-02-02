<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class ImageAttributeTranslation extends AbstractAttributeTranslation
{
    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $alt;
    public function getAlt():?string     { return $this->alt; }
    public function setAlt(?string $alt): self
    {
        $this->alt = $alt;
        return $this;
    }
}