<?php

namespace Base\Entity\Layout\Widget;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute;
use Base\Entity\Layout\Attribute\Hyperlink;
use Base\Entity\Layout\Widget;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\LinkRepository;

/**
 * @ORM\Entity(repositoryClass=LinkRepository::class)
 * @DiscriminatorEntry( value = "hyperlink" )
 */

class Link extends Widget implements IconizeInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __staticIconize() : ?array { return ["fas fa-share-square"]; } 

    /**
     * @ORM\ManyToOne(targetEntity=Hyperlink::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $hyperlink;
    public function getHyperlink(): ?Hyperlink { return $this->hyperlink; }
    public function setHyperlink(?Hyperlink $hyperlink): self
    {
        $this->hyperlink = $hyperlink;
        return $this;
    }

    // public function getIcon()      { return $this->getAttribute()->getIcon(); }
    // public function generateHtml() { return $this->getAttribute()->getValue(); }
    // public function generateUrl()  { return $this->getAttribute()->getValue(); }
}
