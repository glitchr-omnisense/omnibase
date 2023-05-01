<?php

namespace Base\Entity\Layout\Attribute;

use Base\Database\Traits\TranslationTrait;
use Base\Database\TranslationInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class HyperlinkIntl implements TranslationInterface
{
    use TranslationTrait;

    public function isEmpty(): bool
    {
        foreach (get_object_vars($this) as $var => $value) {
            if (in_array($var, ['id', 'translatable', 'locale'], true)) {
                continue;
            }

            if (is_array($value)) {
                $value = array_filter($value);
            }

            if (!empty($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @ORM\Column(type="array")
     */
    protected $value;

    public function getValue(): array
    {
        return $this->value !== null && !is_array($this->value) ? [$this->value] : $this->value ?? [];
    }

    public function setValue($value)
    {
        if ($value !== null && !is_array($value)) {
            $value = [$value];
        }

        $this->value = $value;
        return $this;
    }
}
