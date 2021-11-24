<?php

namespace Base\Entity\Sitemap;

use Doctrine\ORM\Mapping as ORM;

use Base\Validator\Constraints as AssertBase;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\GenerateUuid;
use Base\Annotations\Annotation\Slugify;
use Base\Annotations\Annotation\Uploader;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;

use Base\Repository\Sitemap\WidgetRepository;
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
    
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     *
     * @ORM\Column(type="string", unique=true)
     * @GenerateUuid(version=4)
     */
    protected $uuid;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Slugify(reference="translations.title")
     */
    protected $slug;

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