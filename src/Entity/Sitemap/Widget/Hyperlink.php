<?php

namespace Base\Entity\Sitemap\Widget;

use App\Entity\User\Artist;
use Symfony\Component\Validator\Constraints as Assert;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Entity\Sitemap\Widget;
use Base\Entity\Sitemap\WidgetSlot\Hyperpattern;
use Base\Model\IconizeInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Widget\HyperlinkRepository;

/**
 * @ORM\Entity(repositoryClass=HyperlinkRepository::class)
 * @DiscriminatorEntry( value = "hyperlink" )
 */

class Hyperlink extends Widget implements IconizeInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __staticIconize() : ?array { return ["fas fa-link"]; } 
   
    /**
     * @ORM\ManyToOne(targetEntity=Hyperpattern::class, inversedBy="hyperlinks")
     * @ORM\JoinColumn(nullable=false)
     */
    private $hyperpattern;
    public function getHyperpattern(): ?Hyperpattern { return $this->hyperpattern; }
    public function setHyperpattern(?Hyperpattern $hyperpattern): self
    {
        $this->hyperpattern = $hyperpattern;
        return $this;
    }

    /**
     * @ORM\Column(type="array")
     */
    protected $variables;

    /**
     * @ORM\ManyToOne(targetEntity=Artist::class, inversedBy="socialNetworks")
     * @ORM\JoinColumn(nullable=false)
     */
    private $artist;

    public function getVariables(): string { return $this->variables; }
    public function setVariables($variables)
    {
        $this->variables = $variables;
        return $this;
    }

    public function getIcon() { return $this->getHyperpattern()->getIcon(); }
    public function generateHtml() { return $this->getHyperpattern()->generateHtml($this->variables); }
    public function generateUrl() { return $this->getHyperpattern()->generateUrl($this->variables); }
}