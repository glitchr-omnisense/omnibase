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
class AttributeIntl implements TranslationInterface
{
    use TranslationTrait { isEmpty as _isEmpty; }

    public function isEmpty(): bool { return $this->_isEmpty([], fn($n,$v) => is_array($v) && array_filter($v) != []); }

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
