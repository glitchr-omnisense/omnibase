<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Doctrine\ORM\Mapping as ORM;

use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;

/**
 * @ORM\Entity()
 */
class HyperpatternAttributeTranslation extends AbstractAttributeTranslation
{
    /**
     * @ORM\Column(type="array")
     */
    protected $placeholder;
    public function getPlaceholder():array     { return $this->placeholder; }
    public function setPlaceholder(array $placeholder): self
    {
        $this->placeholder = $placeholder;
        return $this;
    }
}