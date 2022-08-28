<?php

namespace Base\Entity\Layout\Attribute\Adapter;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Field\Type\ImageType;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\ImageAdapterRepository;

/**
 * @ORM\Entity(repositoryClass=ImageAdapterRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @DiscriminatorEntry( value = "image" )
 */

class ImageAdapter extends AbstractAdapter
{
    public static function __iconizeStatic() : ?array { return ["fas fa-image"]; }

    public static function getType(): string { return ImageType::class; }
    public function getOptions(): array { return [/*"alt" => ["label" => "Nom"]*/ ]; }

    public function resolve(mixed $value): mixed { return $value; }
}
