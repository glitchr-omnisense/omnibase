<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Field\Type\ArrayType;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Abstract\ArrayAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=ArrayAttributeRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @DiscriminatorEntry( value = "array" )
 */

class ArrayAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __iconizeStatic() : ?array { return ["fas fa-clipboard-list"]; }

    public function __construct(string $label, ?string $path = null, ?int $length = null)
    {
        parent::__construct($label, $path);
        $this->setLength($length);
    }

    /**
     * @ORM\Column(type="integer", nullable = true)
     */
    protected $length;
    public function getLength():?int     { return $this->length; }
    public function setLength(?int $length)
    {
        $this->length = $length;
        return $this;
    }

    public static function getType(): string { return ArrayType::class; }
    public function getOptions(): array { return ["length" => $this->getLength()]; }
    public function resolve(mixed $value): mixed { return $value; }
}
