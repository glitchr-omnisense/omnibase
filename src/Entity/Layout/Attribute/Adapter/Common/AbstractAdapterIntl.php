<?php

namespace Base\Entity\Layout\Attribute\Adapter\Common;

use Doctrine\ORM\Mapping as ORM;

use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;

#[ORM\Entity]
class AbstractAdapterIntl implements TranslationInterface
{
    use TranslationTrait;

    #[ORM\Column(type: "string", length: 255)]
    protected $label;

    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * @param string|null $label
     * @return $this
     */
    public function setLabel(?string $label): static
    {
        $this->label = $label;
        return $this;
    }

    #[ORM\Column(type: "text", nullable: true)]
    protected $help;

    public function getHelp(): ?string
    {
        return $this->help;
    }

    /**
     * @param string|null $help
     * @return $this
     */
    public function setHelp(?string $help): static
    {
        $this->help = $help;
        return $this;
    }
}
