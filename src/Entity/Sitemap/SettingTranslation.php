<?php

namespace Base\Entity\Sitemap;

use App\Entity\User;
use App\Entity\Thread\Tag;
use App\Entity\Thread\Like;
use App\Entity\Thread\Mention;

use Base\Repository\ThreadRepository;
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
use Base\Database\TranslationInterface;
use Base\Traits\BaseTrait;
use Base\Database\Traits\TranslationTrait;
use Base\Traits\EntityHierarchyTrait;

use Doctrine\ORM\Mapping\DiscriminatorColumn;

/**
 * @ORM\Entity()
 */

class SettingTranslation implements TranslationInterface
{
    use TranslationTrait;

    /**
     * @ORM\Column(type="text")
     * @AssertBase\FileSize(max="1024K", groups={"new", "edit"})
     * @Uploader(storage="local.storage", public="/storage", size="1024K", keepNotFound=true)
     */
    protected $value;

    public function getValue(): ?string     { return Uploader::getPublicPath($this, "value") ?? $this->value; }
    public function getValueFile() { return Uploader::getFile($this, "value"); }
    public function setValue(?string $value)
    {
        $this->value = $value;
        return $this;
    }
}