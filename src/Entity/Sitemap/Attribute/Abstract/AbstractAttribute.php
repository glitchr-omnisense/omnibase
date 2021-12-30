<?php

namespace Base\Entity\Sitemap\Attribute\Abstract;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\Slugify;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Model\AutocompleteInterface;
use Base\Model\IconizeInterface;

use Base\Validator\Constraints as AssertBase;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Attribute\Abstract\AbstractAttributeRepository;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

/**
 * @ORM\Entity(repositoryClass=AbstractAttributeRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * 
 * @ORM\DiscriminatorColumn( name = "type", type = "string" )
 *     @DiscriminatorEntry( value = "abstract" )
 * 
 * @AssertBase\UniqueEntity(fields={"code"}, groups={"new", "edit"})
 */
class AbstractAttribute implements AbstractAttributeInterface, AutocompleteInterface, TranslatableInterface, IconizeInterface
{
    use TranslatableTrait;

    public        function __iconize()       : ?array { return $this->icon ? [$this->icon] : null; } 
    public static function __staticIconize() : ?array { return ["fas fa-share-alt"]; }

    public static function getType(): string { return HiddenType::class; }
    public function getOptions(): array { return []; }
    public function getFormattedValue(string $value): mixed { return $value; }

    public function __autocomplete():?string { return $this->translate()->getLabel(); }
    public function __autocompleteData():array { return ["pattern" => $this->getPattern()]; }
    public function __construct(?string $code = null, ?string $icon = null)
    {
        $this->setCode($code);
        $this->setIcon($icon ?? get_called_class()::__staticIconize()[0]);
    }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }

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