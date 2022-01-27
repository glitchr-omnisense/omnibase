<?php

namespace Base\Entity\Layout\Widget;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\Slugify;
use Base\Database\TranslatableInterface;

use App\Entity\Layout\Widget;
use Base\Database\Annotation\ColumnAlias;
use Base\Model\IconizeInterface;
use Base\Validator\Constraints as AssertBase;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\SlotRepository;

/**
 * @ORM\Entity(repositoryClass=SlotRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * 
 * @DiscriminatorEntry( value = "slot" )
 *
 * @AssertBase\UniqueEntity(fields={"path"}, groups={"new", "edit"})
 * 
 */
class Slot extends Widget implements TranslatableInterface, IconizeInterface
{   
    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-th"]; }

    public function __toString() { return $this->getPath(); }
    public function __construct(?string $path = null)
    {
        $this->widgets = new ArrayCollection();
        $this->path    = $path;
    }

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @AssertBase\NotBlank(groups={"new", "edit"})
     * @Slugify(reference="translations.title", separator=".")
     */
    protected $path;
    public function getPath(): string { return $this->path; }
    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @ORM\ManyToMany(targetEntity=Widget::class)
     */
    protected $widgets;

    /**
     * @return Collection|Widget[]
     */
    public function getWidgets(): Collection { return $this->widgets; }
    public function addWidget(Widget $widget): self
    {
        if(!$this->widgets->contains($widget)) {
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