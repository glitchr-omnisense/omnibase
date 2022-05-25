<?php

namespace Base\Entity\Layout\Attribute\Common;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Entity\Layout\Attribute\Abstract\AbstractAttribute;
use Base\Entity\Layout\AttributeInterface;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Common\AttributeRepository;

/**
 * @ORM\Entity(repositoryClass=BaseAttributeRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * 
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * 
 * @ORM\DiscriminatorColumn( name = "context", type = "string" )
 *     @DiscriminatorEntry(value="attribute_base")
 */
abstract class BaseAttribute implements IconizeInterface, AttributeInterface
{
    public        function __iconize()       : ?array { return $this->adapter ? $this->adapter->__iconizeStatic() : null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-share-alt"]; }

    public function __toString() 
    {
        return $this->getId() ? "<b>".($this->getAdapter() ? $this->getAdapter() : "Attribute")." #".$this->getId()."</b>" : get_class($this); 
    }

    public function __construct(AbstractAttribute $adapter)
    {
        $this->setAdapter($adapter);
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
     * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $adapter;
    public function getAdapter(): ?AbstractAttribute { return $this->adapter; }
    public function setAdapter(?AbstractAttribute $adapter): self
    {
        $this->adapter = $adapter;
        return $this;
    }

    public function getCode(): ?string { return $this->getAdapter()->getCode(); }
    public function getType(): ?string { return get_class($this->getAdapter()); }
    public function getOptions(): array { return $this->getAdapter()->getOptions(); }

}