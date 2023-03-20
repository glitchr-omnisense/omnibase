<?php

namespace Base\Entity\Layout\Widget\Set;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\Annotation\OrderColumn;
use Base\Entity\Layout\Widget\Set\SetInterface;
use Base\Entity\Layout\Widget;
use Base\Service\Model\IconizeInterface;
use Base\Service\Model\LinkableInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\Set\MenuRepository;

use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=MenuRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry
 */

class Menu extends Widget implements IconizeInterface, SetInterface
{
    public function __iconize(): ?array
    {
        return null;
    }
    public static function __iconizeStatic(): ?array
    {
        return ["fas fa-compass"];
    }

    public function __construct(?string $title = null)
    {
        parent::__construct($title);
        $this->items = new ArrayCollection();
    }

    /**
     * @ORM\ManyToMany(targetEntity=Widget::class, cascade={"persist"})
     * @OrderColumn
     */
    protected $items;
    public function getItems(): Collection
    {
        return $this->items;
    }
    public function addItem(Widget $item): self
    {
        if (!$this->items->contains($item) && class_implements_interface($item, LinkableInterface::class)) {
            $this->items[] = $item;
        }

        return $this;
    }

    public function removeItem(Widget $item): self
    {
        $this->items->removeElement($item);

        return $this;
    }
}
