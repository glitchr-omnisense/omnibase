<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Field\Type\MoneyType;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Abstract\MoneyAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=MoneyAttributeRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @DiscriminatorEntry( value = "money" )
 */

class MoneyAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __iconizeStatic() : ?array { return ["fas fa-money-bill-wave"]; }

    public static function getType(): string { return MoneyType::class; }
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
