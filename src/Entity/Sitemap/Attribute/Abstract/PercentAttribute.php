<?php

namespace Base\Entity\Sitemap\Attribute\Abstract;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Model\IconizeInterface;
use Symfony\Component\Form\Extension\Core\Type\PercentType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Attribute\Abstract\PercentAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=PercentAttributeRepository::class)
 * @DiscriminatorEntry( value = "percent" )
 */

class PercentAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __staticIconize() : ?array { return ["fas fa-percent"]; } 

    public static function getType(): string { return PercentType::class; }
    public function getOptions(): array { return []; }

    public function __construct(?string $code = null, ?string $icon = null, int $epsilon = 4, int $scale = 2)
    {
        parent::__construct($code, $icon);
        $this->setPrecision($epsilon);
        $this->setScale($scale);
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $epsilon;

    public function getPrecision():int     { return $this->epsilon; }
    public function setPrecision(int $epsilon)
    {
        $this->epsilon = $epsilon;
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
