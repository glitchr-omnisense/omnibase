<?php

namespace Base\Entity\Layout\Widget;

use Base\Validator\Constraints as AssertBase;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\Slugify;
use Base\Entity\Layout\Widget;
use Base\Model\IconizeInterface;
use Base\Model\UrlInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\PageRepository;
use Base\Service\BaseService;

/**
 * @ORM\Entity(repositoryClass=PageRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE") 
 * @DiscriminatorEntry
 *
 * @AssertBase\UniqueEntity(fields={"slug"}, groups={"new", "edit"})
 */

class Page extends Widget implements IconizeInterface, UrlInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-file-alt"]; } 

    public function __toUrl(): ?string
    {
        return [$this->getTwigExtension()->getRoutingExtension()->getPath(
            "widget_page",
            ["slug" => $this->getSlug()]
        )];
    }

    public function __toString() { return $this->getTitle(); }
    
    public function __construct(string $title, ?string $slug = null)
    {
        parent::__construct($title);
        $this->setSlug($slug);
    }

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Slugify(reference="translations.title")
     * @AssertBase\NotBlank(groups={"new", "edit"})
     */
    protected $slug;
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }
    
    /**
     * Add article content with reshaped titles
     */
    public const MAX_ANCHOR = 6;
    public function getContentWithAnchors(array $options = [], $suffix = "", $max = self::MAX_ANCHOR): ?string
    {
        $max = min($max, self::MAX_ANCHOR);
        return preg_replace_callback("/\<(h[1-".$max."])\>([^\<\>]*)\<\/h[1-".$max."]\>/", function ($match) use ($suffix, $options) {

            $tag = $match[1];
            $content = $match[2];
            $slug = strtolower($this->getSlugger()->slug($content));

            $options["attr"]["class"] = $options["attr"]["class"] ?? "";
            $options["attr"]["class"] = trim($options["attr"]["class"] . " anchor");
            
            return "<".$tag." ".html_attributes($options["row_attr"] ?? [], ["id" => $slug])."><a ".html_attributes($options["attr"] ?? [])." href='#" . $slug . "'>".$content."</a><a href='#" . $slug . "'>".$suffix."</a></".$tag.">";

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
                "slug"  => strtolower($this->getSlugger()->slug($match[2])),
                "title" => $match[2]
            ];

        }, $this->content);

        return $headlines;
    }
}
