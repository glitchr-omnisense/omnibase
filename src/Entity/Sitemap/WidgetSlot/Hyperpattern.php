<?php

namespace Base\Entity\Sitemap\WidgetSlot;

use Symfony\Component\Validator\Constraints as Assert;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\Slugify;
use Base\Entity\Sitemap\WidgetSlot;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\WidgetSlot\HyperpatternRepository;

/**
 * @ORM\Entity(repositoryClass=HyperpatternRepository::class)
 * @DiscriminatorEntry( value = "hyperpattern" )
 */

class Hyperpattern extends WidgetSlot
{
    protected const __PREFIX__ = "app.hyperlink";
    public function __construct(string $name = "website", string $icon = "fas fa-laptop", string $pattern = "{0}")
    {
        $this->setName($name);
        $this->setIcon($icon);
        $this->setPattern($pattern);

        $this->setAttribute("class", "widget-hyperlink");
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
     * @Assert\Url()
     */
    protected $pattern;
    public function getPattern(): string { return $this->pattern; }
    public function setPattern(string $pattern = "{0}")
    {
        $this->pattern = $pattern;
        return $this;
    }

    public function getNumberOfArguments():int { return preg_match_all('/\{[0-9]*\}/i', $this->getPattern()); }
    public function generate(...$replace): string
    {
        $search = [];
        foreach($replace as $index => $_)
            $search[] = "{".$index."}";

        $subject = $this->getPattern();
        $url = str_replace($search, $replace, $subject);
        $url = preg_replace('\{[0-9]*\}', '', $url); // Remove missing entries
        
        $icon = $this->getIcon();
        $class = $this->getAttribute("class");
        
        return "<a class='".$class."' href='".$url."'><i class='".$icon."'></a>";
    }
}