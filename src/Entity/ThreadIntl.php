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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string|null $title
     * @return $this
     */
    /**
     * @param string|null $title
     * @return $this
     */
    public function setTitle(?string $title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $headline;

    public function getHeadline(bool $fallback = false): ?string
    {
        return $this->headline ?? ($fallback ? $this->title : null);
    }

    /**
     * @param string|null $headline
     * @return $this
     */
    /**
     * @param string|null $headline
     * @return $this
     */
    public function setHeadline(?string $headline)
    {
        $this->headline = $headline;

        return $this;
    }

    /**
     * @ORM\Column(type="array")
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
    protected $excerpt;

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    /**
     * @param string|null $excerpt
     * @return $this
     */
    /**
     * @param string|null $excerpt
     * @return $this
     */
    public function setExcerpt(?string $excerpt)
    {
        $this->excerpt = $excerpt;

        return $this;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $content;

    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * @param string|null $content
     * @return $this
     */
    /**
     * @param string|null $content
     * @return $this
     */
    public function setContent(?string $content)
    {
        $this->content = $content;

        return $this;
    }
}
