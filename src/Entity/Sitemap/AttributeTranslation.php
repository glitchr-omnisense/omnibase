<?php

namespace Base\Entity\Sitemap;

use Doctrine\ORM\Mapping as ORM;

use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;

/**
 * @ORM\Entity()
 */
class AttributeTranslation implements TranslationInterface
{
    use TranslationTrait;

    /**
     * @ORM\Column(type="text")
     */
    protected $value;

    public function getValue():mixed     { return $this->value; }
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }
}