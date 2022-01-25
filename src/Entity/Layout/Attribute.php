<?php

namespace Base\Entity\Layout;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Entity\Layout\Attribute\Abstract\AbstractAttribute;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\AttributeRepository;

/**
 * @ORM\Entity(repositoryClass=AttributeRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * 
 * @ORM\DiscriminatorColumn( name = "context", type = "string" )
 *     @DiscriminatorEntry( value = "generic" )
 */

class Attribute implements TranslatableInterface, IconizeInterface
{
    use TranslatableTrait;

    public        function __iconize()       : ?array { return $this->attributePattern ? $this->attributePattern->__iconize() : null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-share-alt"]; }

    public function __construct(AbstractAttribute $attributePattern)
    {
        $this->setAttributePattern($attributePattern);
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
    public function getFormattedValue(?string $locale = null): ?string 
    {
        return $this->attributePattern->getFormattedValue($this->translate($locale)->getValue());
    }
}