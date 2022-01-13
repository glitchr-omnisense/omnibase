<?php

namespace App\Entity\Layout;

use App\Entity\Layout\ImageCrop;
use Base\Validator\Constraints as AssertBase;

use Base\Annotations\Annotation\Uploader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\Layout\ImageRepository;

/**
 * @ORM\Entity(repositoryClass=ImageRepository::class)
 */
class Image
{
    public function __construct() { $this->crops = new ArrayCollection(); }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\Column(type="text")
     */
    protected $name;
    public function getIdentifier(): ?string { return $this->name; }
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @ORM\Column(type="text")
     * @AssertBase\FileSize(max="2MB", groups={"new", "edit"})
     * @Uploader(storage="local.storage", public="/storage", size="2MB", keepNotFound=true)
     */
    protected $file;
    public function getFile(): ?string { return $this->file; }
    public function setFile(string $file): self
    {
        $this->file = $file;

        return $this;
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $fileSize;
    public function getFileSize(): ?string { return $this->fileSize; }
    public function setFileSize(string $fileSize): self
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    /**
     * @ORM\Column(type="text")
     */
    protected $mimeType;
    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    /**
     * @ORM\OneToMany(targetEntity=ImageCrop::class, mappedBy="image")
     */
    protected $crops;
    public function getImageCrops(): Collection { return $this->crops; }
    public function addImageCrop(ImageCrop $crop): self
    {
        if (!$this->crops->contains($crop)) {
            $this->crops[] = $crop;
            $crop->setImage($this);
        }

        return $this;
    }

    public function removeImageCrop(ImageCrop $crop): self
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
