<?php

namespace Base\Entity\Layout\Attribute\Common;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;
use Base\Service\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Common\AbstractAttributeRepository;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=AbstractAttributeRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 *
 * @ORM\DiscriminatorColumn( name = "context", type = "string" )
 *     @DiscriminatorEntry(value="abstract")
 */
abstract class AbstractAttribute implements IconizeInterface, AttributeInterface
{
    public        function __iconize()       : ?array { return $this->adapter ? $this->adapter->__iconizeStatic() : null; }
    public static function __iconizeStatic() : ?array { return ["fas fa-share-alt"]; }

    public function __toString()
    {
        return $this->getId() ? "<b>".($this->getAdapter() ?? "Attribute")." #".$this->getId()."</b>" : get_class($this);
    }

    public function __construct(AbstractAdapter $adapter) { $this->setAdapter($adapter); }

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;
    public function getId(): ?int { return $this->id; }

    /**
     * @ORM\ManyToOne(targetEntity=AbstractAdapter::class, inversedBy="attributes")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $adapter;
    public function getAdapter(): ?AbstractAdapter { return $this->adapter; }
    public function setAdapter(?AbstractAdapter $adapter): self
    {
        $this->adapter = $adapter;
        return $this;
    }

    public function getCode(): ?string { return $this->getAdapter()->getCode(); }
    public function getType(): ?string { return get_class($this->getAdapter()); }
    public function getOptions(): array { return $this->getAdapter()->getOptions(); }
}