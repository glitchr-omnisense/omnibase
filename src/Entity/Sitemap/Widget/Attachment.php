<?php

namespace Base\Entity\Sitemap\Widget;

use Base\Validator\Constraints as AssertBase;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\Slugify;
use Base\Annotations\Annotation\Uploader;
use Base\Entity\Sitemap\Widget;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Widget\AttachmentRepository;
/**
 * @ORM\Entity(repositoryClass=AttachmentRepository::class)
 * @DiscriminatorEntry( value = "attachment" )
 */

class Attachment extends Widget
{
    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Slugify(reference="translations.title")
     */
    protected $slug;
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    /**
     * @ORM\Column(type="text")
     * @Uploader(storage="local.storage", public="/storage", size="4096K")
     * @AssertBase\FileSize(max="4096K", groups={"new", "edit"})
     */
    protected $file;

    public function getPath() { return Uploader::getPublicPath($this, "file"); }
    public function getFile() { return Uploader::getFile($this, "file"); }
    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }
}