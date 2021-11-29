<?php

namespace Base\Entity\Sitemap\WidgetSlot;

use Symfony\Component\Validator\Constraints as Assert;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Entity\Sitemap\WidgetSlot;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\WidgetSlot\SocialRepository;

/**
 * @ORM\Entity(repositoryClass=SocialRepository::class)
 * @DiscriminatorEntry( value = "social" )
 */

class Social extends WidgetSlot
{
    public function __construct(string $name = "unnamed", string $icon = "fas fa-share-alt", string $urlPattern = "{0}")
    {
        $this->setName($name);
        $this->setIcon($icon);

        $this->setUrlPattern($urlPattern);
        $this->setAttribute("class", "widget-social");
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
     * @ORM\Column(type="string", length=255)
     */
    protected $socialName;
    public function getSocialName(): string { return $this->socialName; }
    public function setSocialName(string $socialName): self
    {
        parent::setName("app.social.".$socialName);
        $this->socialName = $socialName;

        return $this;
    }

    /**
     * @ORM\Column(type="text")
     * @Assert\Url()
     */
    protected $urlPattern;
    public function getUrlPattern(): string { return $this->urlPattern; }
    public function setUrlPattern(string $urlPattern = "{0}")
    {
        $this->urlPattern = $urlPattern;
        return $this;
    }

    public function getNumberOfArguments():int { return preg_match_all('/\{[0-9]*\}/i', $this->getUrlPattern()); }
    public function generate(...$replace): string
    {
        $search = [];
        foreach($replace as $index => $_)
            $search[] = "{".$index."}";

        $subject = $this->getUrlPattern();
        $url = str_replace($search, $replace, $subject);
        $url = preg_replace('\{[0-9]*\}', '', $url); // Remove missing entries
        
        $icon = $this->getIcon();
        $class = $this->getAttribute("class");
        
        return "<a class='".$class."' href='".$url."'><i class='".$icon."'></a>";
    }
}