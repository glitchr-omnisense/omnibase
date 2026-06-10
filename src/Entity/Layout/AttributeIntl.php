<?php

namespace Base\Entity\Layout;

use Base\Database\Attribute\Uploader;
use Base\Database\Attribute\Associate;
use Doctrine\ORM\Mapping as ORM;

use Base\Database\Entity\Extension\TranslationInterface;
use Base\Database\Entity\Extension\TranslationTrait;

use Base\Validator\Constraints as AssertBase;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\File;

#[ORM\Entity]
class AttributeIntl implements TranslationInterface
{
    use TranslationTrait {
        TranslationTrait::isEmpty as _isEmpty;
    }

    public function isEmpty(): bool
    {
        return $this->_isEmpty([], fn($n, $v) => is_array($v) && array_filter($v) === []);
    }

    #[ORM\Column(type:"json")]
    #[AssertBase\File(max_size:"2MB", groups:["new", "edit"])]
    #[Uploader(max_size:"2MB", missable:true)]
    #[Associate(metadata:"class")]
    protected $value;

    /**
     * @return array|mixed|File
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
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    #[ORM\Column(type:"string", length:255, nullable:true)]
    protected $class;

    public function getClass(): ?string
    {
        return $this->class;
    }

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
