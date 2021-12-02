<?php

namespace Base\Entity\Sitemap;

use Base\Validator\Constraints as AssertBase;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\GenerateUuid;
use Base\Annotations\Annotation\Uploader;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\WidgetRepository;
use Base\Service\BaseService;

/**
 * @ORM\Entity(repositoryClass=WidgetRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * 
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 *     @DiscriminatorEntry( value = "abstract" )
 */

class Widget implements TranslatableInterface
{   
    use TranslatableTrait;

    protected string $template = "";
    public function getTemplate() 
    {
        if($this->template) return $this->template;

        $defaultTemplate = BaseService::camelToSnakeCase(BaseService::class_basename(get_called_class()));
        $defaultTemplate = "widget/".$defaultTemplate.".html.twig";
        
        return $defaultTemplate;
    }

    public function setTemplate(string $template)
    {
        $this->template = $template;
        return $this;
    }

    public function __toString() { return BaseService::class_basename(get_called_class()) ." [".$this->getId()." - ".$this->getUuid()."]"; }

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
    public function getThumbnail()     { return Uploader::getPublicPath($this, "thumbnail"); }
    public function getThumbnailFile() { return Uploader::getFile($this, "thumbnail"); }
    public function setThumbnail($thumbnail)
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }
}