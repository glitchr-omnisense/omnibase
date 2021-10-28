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
use Base\Enum\ThreadState;
use Base\Database\TranslatableInterface;
use Base\Traits\BaseTrait;
use Base\Traits\EntityHierarchyTrait;
use Base\Database\Traits\TranslatableTrait;

/**
 * @ORM\Entity(repositoryClass=WidgetRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * 
 * @ORM\DiscriminatorColumn( name = "class", type = "string" )
 *     @DiscriminatorEntry( value = "abstract" )
 */

class Menu implements TranslatableInterface
{
    use TranslatableTrait;
    public function getTitle()  : ?string { return $this->translate()->getTitle();   }
    public function setTitle(?string $title) {
        $this->translate()->setTitle($title);  
        return $this; 
    }

    public function getContent(): ?string { return $this->translate()->getContent(); }
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
     * @ORM\Column(type="datetime")
     * @Timestamp(on={"update", "create"})
     */
    protected $updatedAt;

    /**
     * @ORM\Column(type="datetime")
     * @Timestamp(on="create")
     */
    protected $createdAt;

    public function __construct(?string $title = null, ?string $slug = null, WidgetAdapter $adapter = null)
    {
        $this->setTitle($title);
        $this->setSlug($slug);
    }

    public function __toString()
    {
        return $this->getTitle() ?? get_class($this);
    }

    public static function whoAmI(): string
    {
        $array = explode('\\', get_called_class());
        return lcfirst(end($array));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
}