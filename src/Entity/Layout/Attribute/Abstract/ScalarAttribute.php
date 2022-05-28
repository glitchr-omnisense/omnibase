<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Field\Type\NumberType;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Abstract\ScalarAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=ScalarAttributeRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE") 
 * @DiscriminatorEntry( value = "scalar" )
 */

final class ScalarAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __iconizeStatic() : ?array { return ["fas fa-ruler-combined"]; }

    public static function getType(): string { return NumberType::class; }
    public function getOptions(): array { return []; }
    public function resolve(mixed $value): mixed { return $value; }

    public function __construct(string $label = "", ?string $code = null, ?int $unit = null, ?int $minimum = null, ?int $maximum = null)
    {
        parent::__construct($label, $code);

        $this->unit    = $unit;
        $this->minimum = min($minimum, $maximum);
        $this->maximum = max($minimum, $maximum);
    }

    /**
     * @ORM\Column(type="integer", nullable = true)
     */
    protected $unit;
    public function getUnit():?int     { return $this->unit; }
    public function setUnit(?int $unit)
    {
        $this->unit = $unit;
        return $this;
    }

    /**
     * @ORM\Column(type="integer", nullable = true)
     */
    protected $minimum;
    public function getMinimum():?int     { return $this->minimum; }
    public function setMinimum(?int $minimum)
    {
        $this->minimum = $minimum;
        return $this;
    }

    /**
     * @ORM\Column(type="integer", nullable = true)
     */
    protected $maximum;
    public function getMaximum():?int     { return $this->maximum; }
    public function setMaximum(?int $maximum)
    {
        $this->maximum = $maximum;
        return $this;
    }
}
