<?php

namespace Base\Entity;

use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */

class ThreadTranslation implements TranslationInterface
{
    use TranslationTrait;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $title;

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $excerpt;

    public function getExcerpt(): ?string { return $this->excerpt; }
    public function setExcerpt(?string $excerpt): self
    {
        $this->excerpt = $excerpt;

        return $this;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $content;

    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }
}