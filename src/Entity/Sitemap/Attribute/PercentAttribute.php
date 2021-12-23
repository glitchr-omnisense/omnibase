<?php

namespace Base\Entity\Sitemap\Attribute;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Model\IconizeInterface;
use Symfony\Component\Form\Extension\Core\Type\PercentType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Attribute\PercentAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=PercentAttributeRepository::class)
 * @DiscriminatorEntry( value = "percent" )
 */

class PercentAttribute extends AbstractAttribute implements IconizeInterface
{
    public        function __iconize()       : ?array { return null; } 
    public static function __staticIconize() : ?array { return ["fas fa-percent"]; } 

    public static function getType(): string { return PercentType::class; }
    public static function getOptions(): array { return []; }

    public function __construct(string $code, ?string $icon = null, int $precision = NAN, int $scale = NAN)
    {
        parent::__construct($code, $icon);
        $this->setPrecision($precision);
        $this->setScale($scale);
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $precision;

    public function getPrecision():int     { return $this->precision; }
    public function setPrecision(int $precision)
    {
        $this->precision = $precision;
        return $this;
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $scale;

    public function getScale():int     { return $this->scale; }
    public function setScale(int $scale)
    {
        $this->scale = $scale;
        return $this;
    }
}
