<?php

namespace Base\Entity\Layout;

use Base\Annotations\Annotation\Timestamp;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Service\Model\IconizeInterface;

use Base\Validator\Constraints as AssertBase;

use Base\Annotations\Annotation\Uploader;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\TooltipRepository;
use Base\Database\Annotation\Cache;
use DateTimeInterface;

#[ORM\Entity(repositoryClass: TooltipRepository::class)]
#[Cache(usage:"NONSTRICT_READ_WRITE", associations:"ALL")]
class Tooltip implements TranslatableInterface, IconizeInterface
{
    use TranslatableTrait;

    public function __iconize(): ?array
    {
        return $this->getIcon() ? [$this->getIcon()] : null;
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-circle-info"];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getLabel() ?? $this->getUrl() ?? "";
    }

    public function __construct(string $url = "", ?string $label = null)
    {
        $this->setUrl($url);
        $this->setLabel($label);
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    #[ORM\Column(type:"text", nullable:true)]
    #[Uploader(storage:"local.storage", max_size:"2MB", mime_types:["image/*"], missable:true)]
    #[AssertBase\File(max_size:"2MB", mime_types:["image/*"], groups:["new", "edit"])]
    protected $icon;

    /**
     * @return array|mixed|File|null
     * @throws \Exception
     */
    public function getIcon()
    {
        return Uploader::getPublic($this, "icon");
    }

    /**
     * @return array|mixed|File|null
     * @throws FilesystemException
     */
    public function getIconFile()
    {
        return Uploader::get($this, "icon");
    }

    /**
     * @param $icon
     * @return $this
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;
        return $this;
    }

    public function getContent(?string $locale = null, int $inheritanceDepthIfNotSet = 0): ?string
    {
        $content = $this->translate($locale)->getContent();
        if ($inheritanceDepthIfNotSet > 0) {
            $content ??= ($this->getParent() ? $this->getParent()->getContent($locale, --$inheritanceDepthIfNotSet) : null);
        }

        return $content;
    }

    /**
     * @param string|null $content
     * @param string|null $locale
     * @param int $inheritanceDepthIfNotSet
     * @return mixed
     * @throws \Exception
     */
    public function setContent(?string $content, ?string $locale = null, int $inheritanceDepthIfNotSet = 0)
    {
        if ($inheritanceDepthIfNotSet > 0) {
            return $this->translate($locale)->setContent(empty($content) || $content === $this->getParent()->getContent($locale, --$inheritanceDepthIfNotSet) ? null : $content);
        }

        return $this->translate($locale)->setContent($content);
    }


    #[ORM\Column(type:"datetime")]
    #[Timestamp(on:"create")]
    protected $createdAt;
    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    #[ORM\Column(type:"datetime")]
    #[Timestamp(on:["update", "create"])]
    protected $updatedAt;
    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    #[ORM\Column(type:"datetime", nullable:true)]
    protected $startedAt;
    public function getStartedAt(): ?DateTimeInterface
    {
        return $this->startedAt;
    }

    #[ORM\Column(type:"datetime", nullable:true)]
    protected $endedAt;
    public function getEndedAt(): ?DateTimeInterface
    {
        return $this->endedAt;
    }
}
