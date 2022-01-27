<?php

namespace Base\Entity\Layout;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Entity\Layout\Attribute\Abstract\AbstractAttribute;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\AttributeRepository;
use Base\Traits\BaseTrait;

/**
 * @ORM\Entity(repositoryClass=AttributeRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * 
 * @ORM\DiscriminatorColumn( name = "context", type = "string" )
 *     @DiscriminatorEntry( value = "generic" )
 */

class Attribute implements TranslatableInterface, IconizeInterface
{
    use BaseTrait;
    use TranslatableTrait;

    public        function __iconize()       : ?array { return $this->attributePattern ? $this->attributePattern->__iconize() : null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-share-alt"]; }

    public function __construct(AbstractAttribute $attributePattern, mixed $value = null)
    {
        $this->setAttributePattern($attributePattern);
        $this->setValue($value);
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\ManyToOne(targetEntity=AbstractAttribute::class, inversedBy="attributes")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $attributePattern;
    public function getAttributePattern(): ?AbstractAttribute { return $this->attributePattern; }
    public function setAttributePattern(AbstractAttribute $attributePattern): self
    {
        $this->attributePattern = $attributePattern;
        return $this;
    }

    public function getType(): ?string { return get_class($this->getAttributePattern()); }
    public function getOptions(): array { return $this->getAttributePattern()->getOptions(); }
    public function resolve(?string $locale = null): mixed 
    {
        return $this->attributePattern->resolve($this->translate($locale)->getValue()) ?? "";
    }
}