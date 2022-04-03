<?php

namespace Base\Entity\Layout;

use Base\Validator\Constraints as AssertBase;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\GenerateUuid;
use Base\Annotations\Annotation\Uploader;
use Base\Database\Annotation\OrderColumn;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Model\IconizeInterface;
use Doctrine\ORM\Mapping as ORM;

use Base\Repository\Layout\WidgetRepository;
use Base\Traits\BaseTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\Entity(repositoryClass=WidgetRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE") 
 * 
 * @ORM\DiscriminatorColumn( name = "type", type = "string" )
 *     @DiscriminatorEntry
 */

class Widget implements TranslatableInterface, IconizeInterface
{
    use BaseTrait;
    use TranslatableTrait;

    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-cube"]; }

    public function __toString() { return $this->getTitle(); }

    public function __construct(?string $title = null, ?string $excerpt = null, ?string $content = null)
    {
        $this->similars = new ArrayCollection();

        $this->setTitle($title);
        $this->setExcerpt($excerpt);
        $this->setContent($content);
    }

    protected ?string $template;
    public function getTemplate()
    {
        if($this->template) return $this->template;

        $defaultTemplate = camel_to_snake(class_basename(get_called_class()));
        $defaultTemplate = "widget/".$defaultTemplate.".html.twig";

        return $defaultTemplate;
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
    public function getId(): ?int { return $this->id; }

    /**
     *
     * @ORM\Column(type="string", unique=true)
     * @GenerateUuid(version=4)
     */
    protected $uuid;
    public function getUuid() { return $this->uuid; }

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Uploader(storage="local.storage", public="/storage", size="1024K", mime={"image/*"})
     * @AssertBase\FileSize(max="1024K", groups={"new", "edit"})
     */
    protected $thumbnail;
    public function getThumbnail()     { return Uploader::getPublic($this, "thumbnail"); }
    public function getThumbnailFile() { return Uploader::get($this, "thumbnail"); }
    public function setThumbnail($thumbnail)
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    /**
     * @ORM\ManyToMany(targetEntity=Widget::class)
     * @OrderColumn
     */
    protected $similars;
    public function getSimilars(): Collection { return $this->similars; }
    public function addSimilar(Widget $similar): self
    {
        if(!$this->similars->contains($similar))
            $this->similars[] = $similar;

        return $this;
    }

    public function removeSimilar(Widget $similar): self
    {
        $this->similars->removeElement($similar);
        return $this;
    }
}
