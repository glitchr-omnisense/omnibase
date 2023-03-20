<?php

namespace Base\Entity\Thread;

use App\Entity\Thread;
use Base\Annotations\Annotation\Slugify;
use Base\Annotations\Annotation\Uploader;
use Doctrine\Common\Collections\ArrayCollection;
use Base\Database\Annotation\DiscriminatorEntry;

use Base\Database\Traits\TranslatableTrait;

use Base\Database\TranslatableInterface;
use Base\Service\Model\IconizeInterface;
use Base\Service\Model\GraphInterface;
use Doctrine\Common\Collections\Collection;
use Base\Database\Annotation\Cache;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Thread\TaxonRepository;

/**
 * @ORM\Entity(repositoryClass=TaxonRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 *
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 *     @DiscriminatorEntry( value = "abstract" )
 */
class Taxon implements TranslatableInterface, IconizeInterface, GraphInterface
{
    use TranslatableTrait;

    public function __iconize(): ?array
    {
        return $this->getIcon() ? [$this->getIcon()] : null;
    }
    public static function __iconizeStatic(): ?array
    {
        return ["fas fa-sitemap"];
    }

    public function __toString()
    {
        return $this->getLabel() ?? $this->getSlug() ?? get_class($this);
    }
    public function __construct(?string $label = null, ?string $slug = null)
    {
        $this->setLabel($label);
        $this->slug = $slug;

        $this->threads  = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->isVisible  = true;
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @ORM\Column(length=255, unique=true)
     * @Slugify(reference="translations.label")
     */
    protected $slug;
    public function getSlug(): ?string
    {
        return $this->slug;
    }
    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Uploader(storage="local.storage", max_size="2MB", mime_types={"image/*"})
     */
    protected $icon;
    public function getIcon()
    {
        return Uploader::getPublic($this, "icon");
    }
    public function getIconFile()
    {
        return Uploader::get($this, "icon");
    }
    public function setIcon($icon)
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * @ORM\ManyToOne(targetEntity=Taxon::class, inversedBy="children")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $parent;
    public function getParent(): ?self
    {
        return $this->parent;
    }
    public function setParent(?self $parent): self
    {
        $this->parent = $parent;
        if ($this->parent === null) {
            return $this;
        }

        $this->parent->addChild($this);
        return $this;
    }

    public function removeParent(self $parent): self
    {
        if ($this->parent->removeElement($parent)) {
            // set the owning side to null (unless already changed)
            if ($parent->getChildren() === $this) {
                $parent->setChildren(null);
            }
        }

        return $this;
    }

    /**
     * @ORM\OneToMany(targetEntity=Taxon::class, mappedBy="parent", orphanRemoval=true, cascade={"persist"}))
     */
    protected $children;
    public function getChildren(): Collection
    {
        return $this->children;
    }
    public function addChild(self $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children[] = $child;
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): self
    {
        if ($this->children->removeElement($child)) {
            // set the owning side to null (unless already changed)
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    /**
     * @ORM\ManyToMany(targetEntity=Thread::class, mappedBy="taxa")
     */
    protected $threads;
    public function getThreads(): Collection
    {
        return $this->threads;
    }
    public function addThread(Thread $thread): self
    {
        if (!$this->threads->contains($thread)) {
            $this->threads[] = $thread;
            $thread->addTag($this);
        }

        return $this;
    }

    public function removeThread(Thread $thread): self
    {
        if ($this->threads->removeElement($thread)) {
            $thread->removeTag($this);
        }

        return $this;
    }

    /**
     * @ORM\ManyToMany(targetEntity=Taxon::class)
     */
    protected $connexes;
    public function getConnexes(): Collection
    {
        return $this->connexes;
    }
    public function addConnex(self $connex): self
    {
        if (!$this->connexes->contains($connex)) {
            $this->connexes[] = $connex;
        }

        return $this;
    }

    public function removeConnex(self $connex): self
    {
        $this->connexes->removeElement($connex);
        return $this;
    }

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isVisible;
    public function isVisible(): bool
    {
        return $this->isVisible;
    }
    public function markAsVisible(bool $isVisible)
    {
        return $this->setIsVisible($isVisible);
    }
    public function setIsVisible(bool $isVisible)
    {
        $this->isVisible = $isVisible;
        return $this;
    }
}
