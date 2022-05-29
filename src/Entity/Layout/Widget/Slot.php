<?php

namespace Base\Entity\Layout\Widget;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\TranslatableInterface;

use Base\Entity\Layout\Widget;
use Base\Model\IconizeInterface;
use Base\Validator\Constraints as AssertBase;
use Base\Annotations\Annotation\Slugify;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\SlotRepository;

/**
 * @ORM\Entity(repositoryClass=SlotRepository::class)
 * @DiscriminatorEntry
 *
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 *
 */
class Slot extends Widget implements TranslatableInterface, IconizeInterface
{
    public        function __iconize()       : ?array { return null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-th"]; }

    public function __toString() { return $this->getPath(); }
    public function __construct(string $path, ?string $label = null, ?string $help = null)
    {
        parent::__construct();

        $this->path    = $path;
        $this->setLabel($label);
        $this->setHelp($help);
    }

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @AssertBase\NotBlank(groups={"new", "edit"})
     * @Slugify(reference="translations.title", separator=".", keep={"_"})
     */
    protected $path;
    public function getPath(): string { return $this->path; }
    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @ORM\ManyToOne(targetEntity=Widget::class, cascade={"persist"})
     * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
     */
    protected $widget;
    public function getWidget(): ?Widget { return $this->widget; }
    public function setWidget(?Widget $widget): self
    {
        $this->widget = $widget;
        return $this;
    }
}
