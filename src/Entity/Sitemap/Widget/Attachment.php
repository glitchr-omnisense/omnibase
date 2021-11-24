<?php

namespace Base\Entity\Sitemap\Widget;

use Base\Validator\Constraints as AssertBase;

use Base\Annotations\Annotation\DiscriminatorEntry;
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