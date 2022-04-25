<?php

namespace Base\Entity\Layout\Attribute;

use Base\Database\Annotation\ColumnAlias;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute;
use Base\Entity\Layout\Attribute\Abstract\AbstractAttribute;
use Base\Entity\Layout\Attribute\Abstract\HyperpatternAttribute;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\HyperlinkRepository;

/**
 * @ORM\Entity(repositoryClass=HyperlinkRepository::class)
 * @DiscriminatorEntry
 * 
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */

class Hyperlink extends Attribute implements IconizeInterface
{
    public        function __iconize()       : ?array { return $this->getHyperpattern() ? [$this->getHyperpattern()->getIcon()] : null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-link"]; } 

    public function __toString() 
    {
        return "<b>".$this->getHyperpattern()." #".$this->getId()."</b> : ".implode(", ", $this->getValue()); 
    }

    /**
      * @ColumnAlias(column = "adapter")
      * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
      */
    protected $hyperpattern;
    public function getHyperpattern(): HyperpatternAttribute { return $this->hyperpattern; }
    public function setHyperpattern(HyperpatternAttribute $hyperpattern): self
    {
        $this->hyperpattern = $hyperpattern;
        return $this;
    }

    public function generate(?string $locale = null) { return $this->getHyperpattern()->generate(...$this->translate($locale)->getValue()); }
    public function getLabel(): string { return $this->getHyperpattern()->getLabel(); }
}
