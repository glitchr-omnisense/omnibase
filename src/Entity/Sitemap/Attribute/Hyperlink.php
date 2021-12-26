<?php

namespace Base\Entity\Sitemap\Attribute;

use Base\Annotations\Annotation\ColumnAlias;
use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Entity\Sitemap\Attribute;
use Base\Entity\Sitemap\Attribute\Abstract\HyperpatternAttribute;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Attribute\HyperlinkRepository;

/**
 * @ORM\Entity(repositoryClass=HyperlinkRepository::class)
 * @DiscriminatorEntry( value = "hyperlink" )
 */

class Hyperlink extends Attribute implements IconizeInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __staticIconize() : ?array { return ["fas fa-link"]; } 

    /**
      * @ColumnAlias(column = "attributePattern")
      */
    protected $hyperpattern;
    public function getHyperpattern(): HyperpatternAttribute { return $this->hyperpattern; }
    public function setHyperpattern(HyperpatternAttribute $hyperpattern): self
    {
        $this->hyperpattern = $hyperpattern;
        return $this;
    }

    public function getIcon()      { return $this->getHyperpattern()->getIcon(); }
    public function generateHtml(?string $locale = null) { return $this->getHyperpattern()->generateHtml($this->translate($locale)->getValue()); }
    public function generateUrl(?string $locale = null)  { return $this->getHyperpattern()->generateUrl($this->translate($locale)->getValue()); }
}
