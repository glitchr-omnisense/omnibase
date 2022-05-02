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
     * @Uploader(storage="local.storage", public="/storage", max_size="2MB", missable=true)
     */
    protected $value;

    public function getValue() { return Uploader::getPublic($this, "value") ?? $this->value; }
    public function getValueFile() { return Uploader::get($this, "value"); }
    public function setValue($value)
    {
        $this->value  = $this->isEntity($value) ? $value->getId() : $value;
        $this->setClass($this->isEntity($value) ? get_class($value) : null); 

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $class;

    public function getClass(): ?string { return $this->class; }
    public function setClass(?string $class)
    {
        $this->class = $class;
        return $this;
    }
}
