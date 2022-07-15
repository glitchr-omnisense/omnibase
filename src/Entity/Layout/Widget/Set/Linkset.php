<?php

namespace Base\Entity\Layout\Widget\Set;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\Annotation\OrderColumn;
use Base\Entity\Layout\Attribute\Hyperlink;
use Base\Entity\Layout\Widget;
use Base\Model\IconizeInterface;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\Set\LinksetRepository;

/**
 * @ORM\Entity(repositoryClass=LinksetRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @DiscriminatorEntry
 */
class Linkset extends Widget implements IconizeInterface, SetInterface
{
    public        function __iconize()       : ?array { return null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-layer-group"]; }

    public function __construct(string $title, array $hyperlinks = [])
    {
        $this->hyperlinks = new ArrayCollection($hyperlinks);
        parent::__construct($title);
    }

    /**
     * @ORM\ManyToMany(targetEntity=Hyperlink::class, orphanRemoval=true, cascade={"persist"})
     * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
     * @OrderColumn
     */
    protected $hyperlinks;
    public function getHyperlinks(): Collection { return $this->hyperlinks; }
    public function addHyperlink(Hyperlink $hyperlink): self
    {
        if (!$this->hyperlinks->contains($hyperlink)) {
            $this->hyperlinks[] = $hyperlink;
        }

        return $this;
    }

    public function removeHyperlink(Hyperlink $hyperlink): self
    {
        $this->hyperlinks->removeElement($hyperlink);
        return $this;
    }
}