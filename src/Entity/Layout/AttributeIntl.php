<?php

namespace Base\Entity\Layout;

use Base\Annotations\Annotation\Uploader;
use Doctrine\ORM\Mapping as ORM;

use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;

use Base\Validator\Constraints as AssertBase;

/**
 * @ORM\Entity()
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class AttributeIntl implements TranslationInterface
{
    use TranslationTrait;

    // public function isEmpty(): bool
    // {
    //     foreach (get_object_vars($this) as $var => $value) {

    //         if (in_array($var, ['id', 'translatable', 'locale'], true))
    //             continue;

    //         if(is_array($value))
    //             $value = array_filter($value);

    //         if (!empty($value)) return false;
    //     }

    //     return true;
    // }

    /**
     * @ORM\Column(type="array")
     * @AssertBase\File(max_size="2MB", groups={"new", "edit"})
     * @Uploader(storage="local.storage", max_size="2MB", missable=true)
     */
    protected $value;

    public function getValue()     { return Uploader::getPublic($this, "value") ?? $this->value; }
    public function getValueFile() { return Uploader::get($this, "value"); }
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }
}
