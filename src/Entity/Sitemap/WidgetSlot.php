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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

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

    protected const __PREFIX__ = "";

    public function __toString() { return $this->getName(); }
    public function __construct(string $name)
    {
        $this->setName($name);
    }

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
    public function getName(): string 
    { 
       return $this->name;
        return strpos($this->name, get_called_class()::__PREFIX__) === 0 ? 
               substr($this->name, strlen(get_called_class()::__PREFIX__)+1) : $this->name;
    }

    public function setName(string $name): self
    {
        $name = strpos($name, get_called_class()::__PREFIX__) === 0 ? 
                substr($name, strlen(get_called_class()::__PREFIX__)+1) : $name;
        
        $this->name = get_called_class()::__PREFIX__ .".". $name;
        return $this;
    }

    /**
     * @ORM\Column(type="array")
     * e.g. Icon, custom colors,..
     */
    protected $attributes;
    public function getAttributes(): array { return $this->attributes; }
    public function getAttribute($name): ?string { return $this->attributes[$name] ?? null; }
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
     * @ORM\ManyToMany(targetEntity=Widget::class)
     */
    protected $widgets;
    public function getWidgets(): Collection { return $this->widgets; }
    public function addWidget(Widget $widget): self
    {
        if(!$this->widgets->contains($widget))
            $this->widgets[] = $widget;

        return $this;
    }
    public function removeWidget(Widget $widget): self
    {
        $this->widgets->removeElement($widget);
        return $this;
    }
    public function setWidgets($widgets): self
    {
        $this->widgets = $widgets;
        return $this;
    }
}