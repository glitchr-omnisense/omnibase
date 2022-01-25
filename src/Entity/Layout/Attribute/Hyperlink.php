<?php

namespace Base\Entity\Layout\Attribute;

use Base\Database\Annotation\ColumnAlias;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute;
use Base\Entity\Layout\Attribute\Abstract\HyperpatternAttribute;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\HyperlinkRepository;

/**
 * @ORM\Entity(repositoryClass=HyperlinkRepository::class)
 * @DiscriminatorEntry( value = "hyperlink" )
 */

class Hyperlink extends Attribute implements IconizeInterface
{
    public        function __iconize()       : ?array { return $this->getHyperpattern() ? [$this->getHyperpattern()->getIcon()] : null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-link"]; } 

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

    public function generateHtml(?string $locale = null) { return $this->getHyperpattern()->generateHtml($this->translate($locale)->getValue()); }
    public function generateUrl(?string $locale = null)  { return $this->getHyperpattern()->generateUrl($this->translate($locale)->getValue()); }
}
