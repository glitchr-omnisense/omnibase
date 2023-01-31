<?php

namespace Base\Entity;

use Base\Database\Annotation\OrderColumn;
use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;
use Base\Traits\BaseTrait;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\ThreadIntlRepository;

/**
 * @ORM\Entity(repositoryClass=ThreadIntlRepository::class)
 */

class ThreadIntl implements TranslationInterface
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
    public function getHeadline(bool $fallback = false): ?string { return $this->headline ?? ($fallback ? $this->title : null); }
    public function setHeadline(?string $headline)
    {
        $this->headline = $headline;

        return $this;
    }

    /**
     * @ORM\Column(type="json")
     * @OrderColumn
     */
    protected $keywords = [];
    public function getKeywords(): array { return $this->keywords ?? []; }
    public function setKeywords(array $keywords)
    {
        $this->keywords = $keywords;

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