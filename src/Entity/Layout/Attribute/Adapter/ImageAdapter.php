<?php

namespace Base\Entity\Layout\Attribute\Adapter;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;
use Base\Field\Type\ImageType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\ImageAdapterRepository;
use Base\Database\Annotation\Cache;

#[ORM\Entity(repositoryClass: ImageAdapterRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry(value: "image" )]
class ImageAdapter extends AbstractAdapter
{
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-image"];
    }

    public static function getType(): string
    {
        return ImageType::class;
    }
    public function getOptions(): array
    {
        return [/*"alt" => ["label" => "Nom"]*/ ];
    }

    public function resolve(mixed $value): mixed
    {
        return $value;
    }
}
