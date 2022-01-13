<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Doctrine\ORM\Mapping as ORM;

use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;

/**
 * @ORM\Entity()
 */
class AbstractAttributeTranslation implements TranslationInterface
{
    use TranslationTrait;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $label;
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label)
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
}