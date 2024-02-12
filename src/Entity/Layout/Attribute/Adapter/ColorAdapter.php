<?php

namespace Base\Entity\Layout\Attribute\Adapter;

use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;
use Base\Service\Model\Color\Intl\Colors;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Field\Type\ColorType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\ColorAdapterRepository;
use Base\Database\Annotation\Cache;

#[ORM\Entity(repositoryClass:ColorAdapterRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations:"ALL")]
#[DiscriminatorEntry(value: "color" )]
class ColorAdapter extends AbstractAdapter
{
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-tint"];
    }

    public static function getType(): string
    {
        return ColorType::class;
    }
    public function getOptions(): array
    {
        return [];
    }
    public function resolve(mixed $value): mixed
    {
        return $value;
    }

    public function getName(string $locale = null): string
    {
        return Colors::getName($this->color, $locale);
    }
}
