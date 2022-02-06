<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\Slugify;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Model\AutocompleteInterface;
use Base\Model\IconizeInterface;
use Base\Validator\Constraints as AssertBase;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Abstract\AbstractAttributeRepository;
use Base\Entity\Layout\Attribute;

/**
 * @ORM\Entity(repositoryClass=AbstractAttributeRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * 
 * @ORM\DiscriminatorColumn( name = "type", type = "string" )
 *     @DiscriminatorEntry( value = "abstract" )
 * 
 * @AssertBase\UniqueEntity(fields={"code"}, groups={"new", "edit"})
 */
abstract class AbstractAttribute implements AbstractAttributeInterface, AutocompleteInterface, TranslatableInterface, IconizeInterface
{
    use TranslatableTrait;

    public        function __iconize()       : ?array { return $this->icon ? [$this->icon] : null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-share-alt"]; }

    public function __toString() { return $this->getLabel(). " #".$this->getId(); }

    public function __autocomplete():?string { return $this->getLabel(); }
    public function __autocompleteData():array { return $this->getOptions(); }
    public function __construct(string $label = "", ?string $code = null)
    {
        $this->attributes = new ArrayCollection();

        $this->setLabel($label);
        $this->setCode($code);
        $this->setIcon(get_called_class()::__iconizeStatic()[0]);
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\OneToMany(targetEntity=Attribute::class, mappedBy="attributePattern")
     */
    protected $attributes;
    public function getAttributes(): Collection { return $this->attributes; }
    public function setAttributes(Collection $attributes): self
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @AssertBase\NotBlank(groups={"new", "edit"})
     * @Slugify(separator="-")
     */
    protected $code;
    public function getCode(): ?string  { return $this->code; }
    public function setCode(?string $code): self
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $icon;
    public function getIcon(): ?string { return $this->icon; }
    public function setIcon(?string $icon)
    {
        $this->icon = $icon;
        return $this;
    }
}