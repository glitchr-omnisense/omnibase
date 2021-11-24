<?php

namespace Base\Entity\Sitemap;


use Base\Annotations\Annotation\GenerateUuid;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;

use Base\Entity\Sitemap\Widget;

use Base\Validator\Constraints as AssertBase;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\WidgetSlotRepository;
/**
 * @ORM\Entity(repositoryClass=WidgetSlotRepository::class)
 *
 * @AssertBase\UniqueEntity(fields={"name"}, groups={"new", "edit"})
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
     */
    protected $name;
    public function getName(): string { return $this->name; }
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function __construct(string $name = "", ?Widget $widget = null)
    {
        $this->setName($name);
        $this->setWidget($widget);
    }

    // /**
    //  * @ORM\Column(type="array")
    //  */
    // protected $attributes;
    // public function getAttributes(): array { return $this->attributes; }
    // public function setAttributes(array $attributes): self
    // {
    //     $this->attributes = $attributes;

    //     return $this;
    // }

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