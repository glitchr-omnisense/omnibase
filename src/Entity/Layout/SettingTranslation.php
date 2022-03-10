<?php

namespace Base\Entity\Layout;

use Doctrine\ORM\Mapping as ORM;

use Base\Validator\Constraints as AssertBase;

use Base\Database\Annotation\Vault;
use Base\Annotations\Annotation\Uploader;
use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;
use Base\Database\Traits\VaultTrait;

/**
 * @ORM\Entity()
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @Vault(fields={"value"})
 */
class SettingTranslation implements TranslationInterface
{
    use TranslationTrait;
    use VaultTrait;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $label;
    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label)
    {
        $this->label = $label;
        return $this;
    }
    
    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $help;
    public function getHelp(): ?string { return $this->help; }
    public function setHelp(?string $help)
    {
        $this->help = $help;
        return $this;
    }
    
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
