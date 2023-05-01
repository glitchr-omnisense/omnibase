<?php

namespace Base\Entity\Layout\Widget;

use Base\Validator\Constraints as AssertBase;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\Slugify;
use Base\Annotations\Annotation\Uploader;
use Base\Entity\Layout\Widget;
use Base\Service\Model\IconizeInterface;
use Base\Service\Model\LinkableInterface;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Widget\AttachmentRepository;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=AttachmentRepository::class)
 * @DiscriminatorEntry
 *
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @AssertBase\UniqueEntity(fields={"slug"}, groups={"new", "edit"})
 */
class Attachment extends Widget implements IconizeInterface, LinkableInterface
{
    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-paperclip"];
    }

    public function __toLink(array $routeParameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        $routeParameters = array_merge($routeParameters, ["slug" => $this->getSlug()]);

        return $this->getRouter()->generate("widget_attachment", $routeParameters, $referenceType);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getTitle();
    }

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Slugify(reference="translations.title")
     * @AssertBase\NotBlank(groups={"new", "edit"})
     */
    protected $slug;

    public function getSlug(): ?string
    {
        return $this->slug;
    }

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

    /**
     * @return array|mixed|File|null
     * @throws \Exception
     */
    public function getDownload()
    {
        return Uploader::getPublic($this, "download");
    }

    /**
     * @return array|mixed|File|null
     * @throws FilesystemException
     */
    public function getDownloadFile()
    {
        return Uploader::get($this, "download");
    }

    /**
     * @param $file
     * @return $this
     */
    /**
     * @param $file
     * @return $this
     */
    public function setDownload($file)
    {
        $this->file = $file;
        return $this;
    }
}
