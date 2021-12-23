<?php

namespace Base\Entity\Sitemap\Attribute;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Model\IconizeInterface;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Attribute\NumberAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=NumberAttributeRepository::class)
 * @DiscriminatorEntry( value = "number" )
 */

class NumberAttribute extends AbstractAttribute implements IconizeInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __staticIconize() : ?array { return ["fas fa-calculator"]; }

    public static function getType(): string { return NumberType::class; }
    public static function getOptions(): array { return []; }

    public function __construct(string $code, ?string $icon = null, int $minimum = NAN, int $maximum = NAN)
    {
        parent::__construct($code, $icon);
        $this->setMinimum($minimum);
        $this->setMaximum($maximum);
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $minimum;
    public function getMinimum():int     { return $this->min; }
    public function setMinimum(int $min)
    {
        $this->min = $min;
        return $this;
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $maximum;
    public function getMaximum():int     { return $this->max; }
    public function setMaximum(int $max)
    {
        $this->max = $max;
        return $this;
    }
}
