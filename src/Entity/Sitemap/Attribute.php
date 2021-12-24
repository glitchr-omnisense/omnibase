<?php

namespace Base\Entity\Sitemap;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\Slugify;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Entity\Sitemap\Attribute\AbstractAttribute;
use Base\Model\IconizeInterface;

use Base\Validator\Constraints as AssertBase;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\AttributeRepository;
use Doctrine\ORM\Mapping\DiscriminatorColumn;

/**
 * @ORM\Entity(repositoryClass=AttributeRepository::class)
 */
class Attribute implements TranslatableInterface, IconizeInterface
{
    use TranslatableTrait;

    public        function __iconize()       : ?array { return null; } 
    public static function __staticIconize() : ?array { return ["fas fa-share-alt"]; }

    public function __construct(?AbstractAttribute $pattern = null, ?string $value = null, ?string $locale = null)
    {
        $this->setPattern($pattern);
        $this->setValue($value);
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }
}