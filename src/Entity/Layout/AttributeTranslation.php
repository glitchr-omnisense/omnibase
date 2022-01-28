<?php

namespace Base\Entity\Layout;

use Base\Annotations\Annotation\Uploader;
use Doctrine\ORM\Mapping as ORM;

use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;

use Base\Validator\Constraints as AssertBase;

/**
 * @ORM\Entity()
 */
class AttributeTranslation implements TranslationInterface
{
    use TranslationTrait;

    /**
     * @ORM\Column(type="array")
     * @AssertBase\FileSize(max="2MB", groups={"new", "edit"})
     * @Uploader(storage="local.storage", public="/storage", size="2MB", keepNotFound=true)
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