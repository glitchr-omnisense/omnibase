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
    public        function __iconize()       : ?array { return $this->getHyperlink()->__iconize(); } 
    public static function __iconizeStatic() : ?array { return ["fas fa-share-square"]; } 

    public function __construct(Hyperlink $hyperlink) { $this->setHyperlink($hyperlink); }

    /**
     * @ORM\ManyToOne(targetEntity=Hyperlink::class, cascade={"persist"})
     * @ORM\JoinColumn(nullable=false)
     */
    protected $hyperlink;
    public function getHyperlink(): Hyperlink { return $this->hyperlink; }
    public function setHyperlink(Hyperlink $hyperlink)
    {
        $this->hyperlink = $hyperlink;
        return $this;
    }

    public function __toString() 
    {
        $title = $this->getTitle() ?? $this->getHyperlink()->getTitle() ?? $this->__iconize();
        return "<a href='".$this->getHyperlink()->generateUrl()."'>".$title."</a>";
    }
}
