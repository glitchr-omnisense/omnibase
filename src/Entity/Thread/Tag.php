<?php

namespace Base\Entity\Thread;

use App\Entity\Thread;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Base\Validator\Constraints as AssertBase;
use Base\Annotations\Annotation\Slugify;
use Base\Annotations\Annotation\Uploader;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\Traits\TranslatableTrait;
use Base\Database\TranslatableInterface;
use Base\Service\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Thread\TagRepository;

/**
 * @ORM\Entity(repositoryClass=TagRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 *
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 *     @DiscriminatorEntry( value = "abstract" )
 */
class Tag implements TranslatableInterface, IconizeInterface
{
    use TranslatableTrait;

    public        function __iconize()       : ?array { return $this->getIcon() ? [$this->getIcon()] : null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-tags"]; }

    public function __toString() { return $this->getLabel() ?? $this->getSlug() ?? get_class($this); }

    public function __construct(?string $label = null, ?string $slug = null)
    {
        $this->setLabel($label);
        $this->slug = $slug;

        $this->threads = new ArrayCollection();
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\Column(length=255, unique=true)
     * @Slugify(reference="translations.label")
     */
    protected $slug;
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=9, nullable=true)
     */
    protected $color;
    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $color): self
    {
        $this->color = $color;
        return $this;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Uploader(storage="local.storage", max_size="2MB", mime_types={"image/*"}, missable=true)
     * @AssertBase\File(max_size="2MB", mime_types={"image/*"}, groups={"new", "edit"})
     */
    protected $icon;
    public function getIcon() { return Uploader::getPublic($this, "icon"); }
    public function getIconFile() { return Uploader::get($this, "icon"); }
    public function setIcon($icon)
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * @ORM\ManyToMany(targetEntity=Thread::class, mappedBy="tags")
     */
    protected $threads;
    public function getThreads(): Collection { return $this->threads; }
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
}
