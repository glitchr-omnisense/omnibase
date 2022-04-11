<?php

namespace Base\Traits;

use Base\Entity\Layout\Attribute\Abstract\AbstractAttribute;

trait AttributeTrait
{
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
