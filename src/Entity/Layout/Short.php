<?php

namespace Base\Entity\Layout;

use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Model\IconizeInterface;
use Base\Validator\Constraints as AssertBase;
use Base\Annotations\Annotation\Slugify;
use Base\Model\UrlInterface;

use Base\Annotations\Annotation\Randomize;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\ShortRepository;

/**
 * @ORM\Entity(repositoryClass=ShortRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class Short implements TranslatableInterface, IconizeInterface, UrlInterface
{
    use TranslatableTrait;

    public        function __iconize()       : ?array { return null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-compress-alt fa-rotate-45"]; }

    public function __toUrl():?string { return $this->getUrl(); }
    public function __toString() { return $this->getLabel() ?? $this->getUrl(); }
    public function __construct(string $url = "", ?string $label = null)
    {
        $this->setUrl($url);
        $this->setLabel($label);
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\Column(type="string", length=255)
     * @Randomize
     * 
     * @Slugify(separator="-")
     */
    protected $slug;
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug)
    {
        $this->slug = $slug;
        return $this;
    }
}
