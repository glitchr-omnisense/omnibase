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
use Base\Database\TranslatableInterface;
use Base\Traits\BaseTrait;
use Base\Traits\EntityHierarchyTrait;
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
    public function getTitle()  : ?string { return $this->translate()->getTitle()  ; }
    public function getExcerpt(): ?string { return $this->translate()->getExcerpt(); }
    public function getContent(): ?string { return $this->translate()->getContent(); }
    
    public function setTitle(?string $title) {
        $this->translate()->setTitle($title);  
        return $this; 
    }

    public function setExcerpt(?string $excerpt) { 
        $this->translate()->setExcerpt($excerpt); 
        return $this; 
    }

    public function setContent(?string $content) { 
        $this->translate()->setContent($content); 
        return $this; 
    }

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