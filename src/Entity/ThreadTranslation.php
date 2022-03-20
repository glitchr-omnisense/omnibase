<?php

namespace Base\Entity;

use Base\Database\Factory\EntityExtension;
use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;
use Base\Traits\BaseTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */

class ThreadTranslation implements TranslationInterface
{
    use BaseTrait;
    use TranslationTrait;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $title;
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $headline;
    public function getHeadline(): ?string { return $this->headline ?? $this->title; }
    public function setHeadline(?string $headline)
    {
        $this->headline = $headline;

        return $this;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $excerpt;
    public function getExcerpt(): ?string { return $this->excerpt; }
    public function setExcerpt(?string $excerpt)
    {
        $this->excerpt = $excerpt;

        return $this;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $content;
    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $content)
    {
        $this->content = $content;

        return $this;
    }
}