<?php

namespace Base\Entity\Sitemap\Attribute;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Entity\Sitemap\Widget\Hyperlink;
use Base\Model\IconizeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\Extension\Core\Type\TextType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Attribute\HyperpatternAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=HyperpatternAttributeRepository::class)
 * @DiscriminatorEntry( value = "hyperpattern" )
 */

class HyperpatternAttribute extends AbstractAttribute implements IconizeInterface
{
    public        function __iconize()       : ?array { return [$this->getIcon()]; } 
    public static function __staticIconize() : ?array { return ["fas fa-share-alt"]; }

    public static function getType(): string { return TextType::class; }
    public static function getOptions(): array { return []; }

    public function __toString() { return $this->getPattern(); }
    public function __construct(string $code, ?string $icon = "fas fa-laptop", string $pattern = "https://{0}")
    {
        parent::__construct($code, $icon);
        $this->hyperlinks = new ArrayCollection();

        $this->setPattern($pattern);
    }

    /**
     * @ORM\Column(type="text")
     */
    protected $pattern;
    public function getPattern(): string { return $this->pattern; }
    public function setPattern(string $pattern = "https://{0}")
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
            $hyperlink->setHyperpattern($this);
        }

        return $this;
    }

    public function removeHyperlink(Hyperlink $hyperlink): self
    {
        if ($this->hyperlinks->removeElement($hyperlink)) {
            // set the owning side to null (unless already changed)
            if ($hyperlink->getHyperpattern() === $this) {
                $hyperlink->setHyperpattern(null);
            }
        }

        return $this;
    }
}