<?php

namespace Base\Entity\Layout\Widget;

use Base\Validator\Constraints as AssertBase;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\Slugify;
use Base\Annotations\Annotation\Uploader;
use Base\Entity\Layout\Widget;
use Base\Model\IconizeInterface;
use Base\Model\LinkableInterface;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\AttachmentRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @ORM\Entity(repositoryClass=AttachmentRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE") 
 * @DiscriminatorEntry
 *
 * @AssertBase\UniqueEntity(fields={"slug"}, groups={"new", "edit"})
 */

class Attachment extends Widget implements IconizeInterface, LinkableInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-paperclip"]; } 

    public function __toLink(int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string 
    {
        return $this->getRouter()->generate("widget_attachment", ["slug" => $this->getSlug()], $referenceType);
    }

    public function __toString() { return $this->getTitle(); }

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Slugify(reference="translations.title")
     * @AssertBase\NotBlank(groups={"new", "edit"})
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
     * @Uploader(storage="local.storage", max_size="4096K")
     * @AssertBase\File(max_size="4096K", groups={"new", "edit"})
     */
    protected $download;
    public function getDownload() { return Uploader::getPublic($this, "download"); }
    public function getDownloadFile() { return Uploader::get($this, "download"); }
    public function setDownload($file)
    {
        $this->file = $file;
        return $this;
    }
}
