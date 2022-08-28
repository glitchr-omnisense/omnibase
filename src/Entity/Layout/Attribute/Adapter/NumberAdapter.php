<?php

namespace Base\Entity\Layout\Attribute\Adapter;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Field\Type\NumberType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\NumberAdapterRepository;

/**
 * @ORM\Entity(repositoryClass=NumberAdapterRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @DiscriminatorEntry( value = "number" )
 */

class NumberAdapter extends AbstractAdapter
{
    public static function __iconizeStatic() : ?array { return ["fas fa-calculator"]; }

    public static function getType(): string { return NumberType::class; }
    public function getOptions(): array { return []; }
    public function resolve(mixed $value): mixed { return $value; }

    public function __construct(string $label = "", ?string $code = null, ?int $minimum = null, ?int $maximum = null)
    {
        parent::__construct($label, $code);
        $this->setMinimum($minimum);
        $this->setMaximum($maximum);
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
