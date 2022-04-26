<?php

namespace Base\Entity\Thread;

use Doctrine\Common\Collections\ArrayCollection;
use Base\Database\Annotation\DiscriminatorEntry;

use Base\Database\Traits\TranslatableTrait;

use Base\Database\TranslatableInterface;
use Base\Entity\Thread\Tag;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\Marketplace\TaxonRepository;

/**
 * @ORM\Entity(repositoryClass=TaxonRepository::class)
 * @DiscriminatorEntry
 */

abstract class Taxon extends Tag implements TranslatableInterface
{
    use TranslatableTrait;

    public static function __iconizeStatic() : ?array { return ['fas fa-sitemap']; } 

    public function __construct(?string $label, ?string $slug = null)
    {
        parent::__construct($label, $slug);
        $this->children = new ArrayCollection();
    }

    /**
     * @ORM\ManyToOne(targetEntity=Taxon::class, inversedBy="children")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $parent;
    public function getParent(): ?self { return $this->parent; }
    public function setParent(?self $parent): self
    {
        $this->parent = $parent;
        if ($this->parent === null) return $this;

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
    public function getChildren(): ?self { return $this->children; }
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
}
