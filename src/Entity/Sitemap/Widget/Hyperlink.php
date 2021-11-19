<?php

namespace Base\Entity\Sitemap\Widget;

use App\Entity\User;
use App\Entity\Thread\Tag;
use App\Entity\Thread\Like;
use App\Entity\Thread\Mention;

use Base\Repository\Sitemap\Widget\HyperlinkRepository;
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
use Base\Entity\Sitemap\Widget;

/**
 * @ORM\Entity(repositoryClass=HyperlinkRepository::class)
 * @DiscriminatorEntry( value = "hyperlink" )
 */

class Hyperlink extends Widget implements TranslatableInterface
{   
    use TranslatableTrait;

    public function getLabel(): ?string { return $this->translate()->getLabel(); }
    public function setLabel(?string $excerpt) { 
        $this->translate()->setLabel($excerpt); 
        return $this; 
    }

    /**
     * @ORM\Column(type="text")
     * @Assert\Url
     */
    protected $url;

    public function getUrl() { return $this->url; }
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

}