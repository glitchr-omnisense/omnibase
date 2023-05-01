<?php

namespace Base\Entity\Layout;

use Base\Service\Model\CacheableInterface;
use Base\Traits\CacheableTrait;
use Base\Validator\Constraints as AssertBase;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\GenerateUuid;
use Base\Annotations\Annotation\Uploader;
use Base\Database\Annotation\OrderColumn;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Service\Model\IconizeInterface;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

use Base\Repository\Layout\WidgetRepository;
use Base\Traits\BaseTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Base\Database\Annotation\Cache;
use Base\Annotations\Annotation\Timestamp;

/**
 * @ORM\Entity(repositoryClass=WidgetRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 *
 * @ORM\DiscriminatorColumn( name = "type", type = "string" )
 * @DiscriminatorEntry
 */
class Widget implements TranslatableInterface, IconizeInterface, CacheableInterface
{
    use BaseTrait;
    use TranslatableTrait;
    use CacheableTrait {
        CacheableTrait::__toKey as __toDefaultKey;
    }

    public function __toKey(?string ...$variadic): string
    {
        $variadic[] = $this->getId();
        $variadic[] = $this->getUpdatedAt();
        return $this->__toDefaultKey(...$variadic);
    }

    public function __iconize(): ?array
    {
        return null;
    }

    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-cube"];
    }

    public function __toString()
    {
        return $this->getTitle() ?? $this->getTranslator()->transEntity(self::class) . " #" . $this->getId();
    }

    public function __construct(?string $title = null, ?string $excerpt = null, ?string $content = null)
    {
        $this->connexes = new ArrayCollection();

        $this->setTitle($title);
        $this->setExcerpt($excerpt);
        $this->setContent($content);
    }

    protected $template = null;

    public function getTemplate()
    {
        if ($this->template) {
            return $this->template;
        }

        $defaultTemplate = camel2snake(class_basename(get_called_class()));
        return "widget/" . $defaultTemplate . ".html.twig";
    }

    public function setTemplate(?string $template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     *
     * @ORM\Column(type="string", unique=true)
     * @GenerateUuid(version=4)
     */
    protected $uuid;

    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Uploader(storage="local.storage", max_size="1024KB", mime_types={"image/*"})
     * @AssertBase\File(max_size="1024KB", mime_types={"image/*"}, groups={"new", "edit"})
     */
    protected $thumbnail;

    public function getThumbnail()
    {
        return Uploader::getPublic($this, "thumbnail");
    }

    public function getThumbnailFile()
    {
        return Uploader::get($this, "thumbnail");
    }

    public function setThumbnail($thumbnail)
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    /**
     * @ORM\ManyToMany(targetEntity=Widget::class)
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @OrderColumn
     */
    protected $connexes;

    public function getConnexes(): Collection
    {
        return $this->connexes;
    }

    public function addConnex(Widget $connex): self
    {
        if (!$this->connexes->contains($connex)) {
            $this->connexes[] = $connex;
        }

        return $this;
    }

    public function removeConnex(Widget $connex): self
    {
        $this->connexes->removeElement($connex);
        return $this;
    }

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Timestamp(on="create")
     */
    protected $createdAt;

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Timestamp(on={"update", "create"})
     */
    protected $updatedAt;

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }
}
