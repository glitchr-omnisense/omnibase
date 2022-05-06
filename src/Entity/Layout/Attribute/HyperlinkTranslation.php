<?php

namespace Base\Entity\Layout\Attribute;

use Doctrine\ORM\Mapping as ORM;

use Base\Entity\Layout\AttributeTranslation;

/**
 * @ORM\Entity()
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE") 
 */
class HyperlinkTranslation extends AttributeTranslation
{
    public function getValue():array {
        return $this->value !== null && !is_array($this->value) ? [$this->value] : $this->value ?? [];
    }

    public function setValue($value)
    {
        if($value !== null && !is_array($value)) 
           $value = [$value];

        $this->value = $value;
        return $this;
    }
}