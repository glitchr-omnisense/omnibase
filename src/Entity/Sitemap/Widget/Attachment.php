<?php

namespace Base\Entity\Sitemap\Widget;

use App\Entity\User;
use App\Entity\Thread\Tag;
use App\Entity\Thread\Like;
use App\Entity\Thread\Mention;

use Base\Repository\Sitemap\Widget\AttachmentRepository;
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
use Base\Entity\Sitemap\Widget;

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