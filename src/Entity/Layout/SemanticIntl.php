<?php

namespace Base\Entity\Layout;

use Base\Database\Annotation\OrderColumn;
use Doctrine\ORM\Mapping as ORM;

use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;
use Base\Traits\BaseTrait;

/**
 * @ORM\Entity()
 */
class SemanticIntl implements TranslationInterface
{
    use BaseTrait;
    use TranslationTrait;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $label;

    public function getLabel(): ?string
    {
        return $this->label;
    }

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
}
