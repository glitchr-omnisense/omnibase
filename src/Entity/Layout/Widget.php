<?php

namespace Base\Entity\Layout;

use Base\Validator\Constraints as AssertBase;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\GenerateUuid;
use Base\Annotations\Annotation\Uploader;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Model\IconizeInterface;
use Doctrine\ORM\Mapping as ORM;

use Base\Repository\Layout\WidgetRepository;
use Base\Traits\BaseTrait;

/**
 * @ORM\Entity(repositoryClass=WidgetRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * 
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 *     @DiscriminatorEntry( value = "abstract" )
 */

class Widget implements TranslatableInterface, IconizeInterface
{
    use BaseTrait;
    use TranslatableTrait;

    public        function __iconize()       : ?array { return null; } 
    public static function __staticIconize() : ?array { return ["fas fa-cube"]; }

    protected string $template = "";
    public function getTemplate()
    {
        if($this->template) return $this->template;

        $defaultTemplate = camel_to_snake(class_basename(get_called_class()));
        $defaultTemplate = "widget/".$defaultTemplate.".html.twig";

        return $defaultTemplate;
    }

    public function setTemplate(string $template)
    {
        $this->template = $template;
        return $this;
    }

    public function __toString() { return class_basename(get_called_class()) ." [".$this->getId()." - ".$this->getUuid()."]"; }

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
}
