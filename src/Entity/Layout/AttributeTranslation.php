<?php

namespace Base\Entity\Layout;

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
     * @ORM\Column(type="array")
     */
    protected $value;

    public function getValue():mixed     { return $this->value; }
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }
}