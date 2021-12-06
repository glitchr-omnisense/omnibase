<?php

namespace Base\Entity\Sitemap\WidgetSlot;

use Symfony\Component\Validator\Constraints as Assert;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\Slugify;
use Base\Entity\Sitemap\Widget\Hyperlink;
use Base\Entity\Sitemap\WidgetSlot;
use Base\Model\IconizeInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\WidgetSlot\HyperpatternRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\Entity(repositoryClass=HyperpatternRepository::class)
 * @DiscriminatorEntry( value = "hyperpattern" )
 */

class Hyperpattern extends WidgetSlot implements IconizeInterface
{
    public static function __iconize(): array { return ["fas fa-share-alt"]; }

    protected const __PREFIX__ = "app.hyperlink";

    public function __toString() { return $this->getPattern(); }
    public function __construct(string $path = "website", string $icon = "fas fa-laptop", string $pattern = "{0}")
    {
        $this->setAttribute("class", "widget-hyperlink");
        $this->setPath($path);
        $this->setIcon($icon);

        $this->hyperlinks = new ArrayCollection();
        $this->setPattern($pattern);
    }

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $icon;
    public function getIcon(): string { return $this->icon; }
    public function setIcon(string $icon)
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * @ORM\Column(type="text")
     */
    protected $pattern;
    public function getPattern(): string { return $this->pattern; }
    public function setPattern(string $pattern = "{0}")
    {
        $this->pattern = $pattern;
        return $this;
    }

    public function getNumberOfArguments():int { return preg_match_all('/\{[0-9]*\}/i', $this->getPattern()); }
    public function generateUrl(...$replace): string
    {
        $search = [];
        foreach($replace as $index => $_)
            $search[] = "{".$index."}";

        $subject = $this->getPattern();
        $url = str_replace($search, $replace, $subject);
        return preg_replace('\{[0-9]*\}', '', $url); // Remove missing entries
    }

    public function generateHtml(...$replace): string
    {
        $url = $this->generateUrl($replace);
        $icon = $this->getIcon();
        $class = $this->getAttribute("class");
        
        return "<a class='".$class."' href='".$url."'><i class='".$icon."'></a>";
    }

    /**
     * @ORM\OneToMany(targetEntity=Hyperlink::class, mappedBy="hyperpattern")
     */
    private $hyperlinks;
    public function getHyperlinks(): Collection { return $this->hyperlinks; }
    public function addHyperlink(Hyperlink $hyperlink): self
    {
        if (!$this->hyperlinks->contains($hyperlink)) {
            $this->hyperlinks[] = $hyperlink;
            $hyperlink->setPattern($this);
        }

        return $this;
    }

    public function removeHyperlink(Hyperlink $hyperlink): self
    {
        if ($this->hyperlinks->removeElement($hyperlink)) {
            // set the owning side to null (unless already changed)
            if ($hyperlink->getPattern() === $this) {
                $hyperlink->setPattern(null);
            }
        }

        return $this;
    }
}