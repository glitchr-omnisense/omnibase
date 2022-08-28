<?php

namespace Base\Entity\Layout\Attribute\Adapter;

use Base\Service\Model\Color\Intl\Colors;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Field\Type\ColorType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\ColorAdapterRepository;

/**
 * @ORM\Entity(repositoryClass=ColorAdapterRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @DiscriminatorEntry( value = "color" )
 */

class ColorAdapter extends AbstractAdapter
{
    public static function __iconizeStatic() : ?array { return ["fas fa-tint"]; }

    public static function getType(): string { return ColorType::class; }
    public function getOptions(): array { return []; }
    public function resolve(mixed $value): mixed { return $value; }

    public function getName(string $locale = null): string
    {
        return Colors::getName($this->color, $locale);
    }
}
