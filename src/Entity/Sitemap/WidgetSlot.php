<?php

namespace Base\Entity\Sitemap;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\GenerateUuid;
use Base\Annotations\Annotation\Slugify;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;

use Base\Entity\Sitemap\Widget;

use Base\Validator\Constraints as AssertBase;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\WidgetSlotRepository;

/**
 * @ORM\Entity(repositoryClass=WidgetSlotRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @AssertBase\UniqueEntity(fields={"name"}, groups={"new", "edit"})
 *
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 *     @DiscriminatorEntry( value = "abstract" )
 */

class WidgetSlot implements TranslatableInterface
{   
    use TranslatableTrait;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }

    /**
     *
     * @ORM\Column(type="string", unique=true)
     * @GenerateUuid(version=4)
     */
    protected $uuid;
    public function getUuid(): string { return $this->uuid; }

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @AssertBase\NotBlank(groups={"new", "edit"})
     * @Slugify(separator=".")
     */
    protected $name;
    public function getName(): string { return $this->name; }
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function __toString() { return $this->getName(); }
    public function __construct(string $name = "", ?Widget $widget = null)
    {
        $this->setName($name);
        $this->setWidget($widget);
    }

    /**
     * @ORM\Column(type="array")
     * e.g. Icon, custom colors,..
     */
    protected $attributes;
    public function getAttributes(): array { return $this->attributes; }
    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;
        return $this;
    }

    public function setAttribute(string $key, $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * @ORM\ManyToOne(targetEntity=Widget::class)
     */
    protected $widget;
    public function getWidget(): ?Widget { return $this->widget; }
    public function setWidget(?Widget $widget): self
    {
        $this->widget = $widget;
        return $this;
    }
}