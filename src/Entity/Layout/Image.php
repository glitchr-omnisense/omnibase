<?php

namespace Base\Entity\Layout;

use Base\Entity\Layout\ImageCrop;
use Base\Validator\Constraints as AssertBase;

use Base\Annotations\Annotation\Uploader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\Layout\ImageRepository;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Model\IconizeInterface;
use Base\Traits\BaseTrait;
use Doctrine\ORM\Mapping\DiscriminatorColumn;

/**
 * @ORM\Entity(repositoryClass=ImageRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @ORM\DiscriminatorColumn( name = "type", type = "string" )
 *     @DiscriminatorEntry
 */
class Image implements IconizeInterface
{
    use BaseTrait;

    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-images"]; }

    public function __toString() { return Uploader::getPublic($this, "source") ?? $this->getService()->getParameterBag("base.image.no_image") ?? ""; }
    public function __construct($src) 
    {
        $this->crops = new ArrayCollection(); 
        $this->setSource($src);
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\Column(type="text")
     * @AssertBase\File(max_size="2MB", groups={"new", "edit"})
     * @Uploader(storage="local.storage", public="/storage", max_size="2MB")
     */
    protected $source;
    public function getSource()     { return Uploader::getPublic($this, "source"); }
    public function getSourceFile() { return Uploader::get($this, "source"); }
    public function setSource($source): self
    {
        $this->source = $source;
        return $this;
    }

    public function get()     { return $this->getSource(); }
    public function getFile() { return $this->getSourceFile(); }
    public function set($source): self { return $this->setSource($source); }

    /**
     * @ORM\OneToMany(targetEntity=ImageCrop::class, mappedBy="image", orphanRemoval=true, cascade={"persist", "remove"})
     */
    protected $crops;
    public function getCrops(): Collection { return $this->crops; }
    public function addCrop(ImageCrop $crop): self
    {
        if (!$this->crops->contains($crop)) {
            $this->crops[] = $crop;
            $crop->setImage($this);
        }

        return $this;
    }

    public function removeCrop(ImageCrop $crop): self
    {
        if ($this->crops->removeElement($crop)) {
            // set the owning side to null (unless already changed)
            if ($crop->getImage() === $this) {
                $crop->setImage(null);
            }
        }

        return $this;
    }
}
