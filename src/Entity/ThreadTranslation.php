<?php

namespace Base\Entity;

use Base\Database\TranslationInterface;
use Base\Database\Traits\TranslationTrait;
use Base\Traits\BaseTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */

class ThreadTranslation implements TranslationInterface
{
    use BaseTrait;
    use TranslationTrait;

    /**
     * @ORM\Column(type="string", length=255)
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


    /**
     * Add article content with reshaped titles
     */
    public const MAX_ANCHOR = 6;
    public function getContentWithAnchor($suffix = "", $max = self::MAX_ANCHOR): ?string
    {
        $max = min($max, self::MAX_ANCHOR);
        return preg_replace_callback("/\<(h[1-".$max."])\>([^\<\>]*)\<\/h[1-".$max."]\>/", function ($match) use ($suffix) {

            $tag = $match[1];
            $content = $match[2];
            $slug = $this->getSlugger()->slug($content);

            return "<".$tag." id='".$slug. "'><a class='anchor' href='#" . $slug . "'>&nbsp".$content."&nbsp</a>$suffix</".$tag.">";

        }, $this->content);
    }

    /**
     * Compute table of content
     */
    public function getTableOfContent($max = 6): array
    {
        $headlines = [];
        $max = min($max, 6);

        preg_replace_callback("/\<(h[1-".$max."])\>([^\<\>]*)\<\/h[1-".$max."]\>/", function ($match) use (&$headlines) {

            $headlines[] = [
                "tag" => $match[1],
                "slug"  => $this->getSlugger()->slug($match[2]),
                "title" => $match[2]
            ];

        }, $this->content);

        return $headlines;
    }
}