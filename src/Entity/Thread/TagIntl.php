<?php

namespace Base\Entity\Thread;

use Doctrine\ORM\Mapping as ORM;
use Base\Database\Annotation\OrderColumn;

use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;

/**
 * @ORM\Entity()
 */

class TagIntl implements TranslationInterface
{
    use TranslationTrait;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $label;
    public function getLabel(): ?string
    {
        return $this->label;
    }
    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @ORM\Column(type="json")
     * @OrderColumn
     */
    protected $keywords = [];

    public function getKeywords(): array
    {
        return $this->keywords ?? [];
    }

    /**
     * @param array $keywords
     * @return $this
     */
    public function setKeywords(array $keywords)
    {
        $this->keywords = $keywords;
        return $this;
    }
    
    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $description;
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
