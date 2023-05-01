<?php

namespace Base\Entity\Layout\Attribute\Common;

use Base\Database\Annotation\Associate;
use Base\Database\Annotation\DiscriminatorEntry;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Common\AbstractActionRepository;

use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=AbstractActionRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 *
 * @ORM\DiscriminatorColumn( name = "context", type = "string" )
 * @DiscriminatorEntry(value="abstract_action")
 */
abstract class AbstractAction extends AbstractAttribute implements ActionInterface
{
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-directions"];
    }

    public function apply(mixed $subject): mixed
    {
        return $this->adapter?->apply($this->getValue(), $subject) ?? $subject;
    }

    /**
     * @ORM\Column(type="array")
     * @Associate(metadata="class")
     */
    protected $value;

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $class;

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function setClass(?string $class)
    {
        $this->class = $class;
        return $this;
    }
}
