<?php

namespace Base\Entity\Sitemap\Attribute\Abstract;

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

    public function getPlaceholder():mixed     { return $this->placeholder; }
    public function setPlaceholder($placeholder)
    {
        $this->placeholder = $placeholder;
        return $this;
    }
}