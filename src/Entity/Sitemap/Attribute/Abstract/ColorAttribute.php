<?php

namespace Base\Entity\Sitemap\Attribute\Abstract;

use Base\Annotations\Annotation\DiscriminatorEntry;
use Base\Field\Type\ColorType;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Sitemap\Attribute\Abstract\ColorAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=ColorAttributeRepository::class)
 * @DiscriminatorEntry( value = "color" )
 */

class ColorAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __staticIconize() : ?array { return ["fas fa-tint"]; }

    public static function getType(): string { return ColorType::class; }
    public static function getOptions(): array { return []; }
}
