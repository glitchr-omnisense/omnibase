<?php

namespace Base\Entity\Layout\Widget;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Widget;
use Base\Model\IconizeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\MenuRepository;

/**
 * @ORM\Entity(repositoryClass=MenuRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE") 
 * @DiscriminatorEntry( value = "menu" )
 */

class Menu extends Widget implements IconizeInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-compass"]; } 

    public function __construct(string $title)
    {
        $this->widgets = new ArrayCollection();
        parent::__construct($title);
    }

    /**
     * @ORM\ManyToMany(targetEntity=Widget::class, cascade={"persist"})
     * @ORM\Cache(usage="NONSTRICT_READ_WRITE") 
     */
    protected $widgets;
    public function getWidgets(): Collection { return $this->widgets; }
    public function addWidget(Widget $widget): self
    {
        if (!$this->widgets->contains($widget)) {
            $this->widgets[] = $widget;
        }

        return $this;
    }

    public function removeWidget(Widget $widget): self
    {
        $this->widgets->removeElement($widget);

        return $this;
    }
}