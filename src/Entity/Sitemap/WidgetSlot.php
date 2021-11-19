<?php

namespace Base\Entity\Sitemap;

use App\Entity\User;
use App\Entity\Thread\Tag;
use App\Entity\Thread\Like;
use App\Entity\Thread\Mention;
use Base\Entity\Sitemap\Widget;
use Base\Repository\Sitemap\WidgetSlotRepository;

use Base\Repository\ThreadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Validator\Constraints as Assert;
use Base\Validator\Constraints as AssertBase;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\GenerateUuid;
use Base\Annotations\Annotation\Timestamp;
use Base\Annotations\Annotation\Slugify;
use Base\Annotations\Annotation\EntityHierarchy;
use Base\Annotations\Annotation\Uploader;
use Base\Enum\ThreadState;
use Base\Database\TranslatableInterface;
use Base\Traits\BaseTrait;
use Base\Traits\EntityHierarchyTrait;
use Base\Database\Traits\TranslatableTrait;

/**
 * @ORM\Entity(repositoryClass=WidgetSlotRepository::class)
 */
class WidgetSlot
{ 
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

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

    /**
     * @ORM\Column(type="array")
     */
    protected $attributes;
    public function getAttributes(): array { return $this->attributes; }
    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $help;
    public function getHelp(): string { return $this->help; }
    public function setHelp(string $help)
    {
        $this->help = $help;
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