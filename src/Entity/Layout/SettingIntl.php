<?php

namespace Base\Entity\Layout;

use Base\Database\Annotation\Associate;
use Doctrine\ORM\Mapping as ORM;

use Base\Database\Annotation\Vault;
use Base\Annotations\Annotation\Uploader;
use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;
use Base\Database\Traits\VaultTrait;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * @ORM\Entity()
 * @Vault(fields={"value"})
 */
class SettingIntl implements TranslationInterface
{
    use TranslationTrait {
        TranslationTrait::isEmpty as _isEmpty;
    }
    use VaultTrait;

    public function isEmpty(): bool
    {
        return $this->value === null;
    }

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $label = null;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * @param string|null $label
     * @return $this
     */
    /**
     * @param string|null $label
     * @return $this
     */
    public function setLabel(?string $label)
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $help = null;

    public function getHelp(): ?string
    {
        return $this->help;
    }

    /**
     * @param string|null $help
     * @return $this
     */
    /**
     * @param string|null $help
     * @return $this
     */
    public function setHelp(?string $help)
    {
        $this->help = $help;
        return $this;
    }

    /**
     * @ORM\Column(type="array")
     * @Uploader(storage="local.storage", max_size="2MB", missable=true)
     * @Associate(metadata="class")
     */
    protected $value = null;

    /**
     * @return array|mixed|File|null
     * @throws \Exception
     */
    public function getValue()
    {
        return Uploader::getPublic($this, "value") ?? $this->value;
    }

    /**
     * @return array|mixed|File|null
     * @throws FilesystemException
     */
    public function getValueFile()
    {
        return Uploader::get($this, "value");
    }

    /**
     * @param $value
     * @return $this
     */
    /**
     * @param $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $class = null;

    public function getClass(): ?string
    {
        return $this->class;
    }

    /**
     * @param string|null $class
     * @return $this
     */
    /**
     * @param string|null $class
     * @return $this
     */
    public function setClass(?string $class)
    {
        $this->class = $class;
        return $this;
    }
}
