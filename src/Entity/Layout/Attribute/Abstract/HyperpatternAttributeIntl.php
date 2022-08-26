<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Base\Entity\Layout\Attribute\Abstract\AbstractAttributeTranslation;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class HyperpatternAttributeIntl extends AbstractAttributeIntl
{
    /**
     * @ORM\Column(type="array")
     */
    protected $placeholder;
    public function getPlaceholder():?array     { return $this->placeholder; }
    public function setPlaceholder(?array $placeholder): self
    {
        $this->placeholder = $placeholder;
        return $this;
    }
}
