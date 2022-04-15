<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Field\Type\ImageType;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Abstract\ImageAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=ImageAttributeRepository::class)
 * @DiscriminatorEntry( value = "image" )
 */

class ImageAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __iconizeStatic() : ?array { return ["fas fa-image"]; }

    public static function getType(): string { return ImageType::class; }
    public function getOptions(): array { return [
        "alt" => $this->getAlt() ?? "",
        "alt_options" => ["label" => "Nom"]
    ]; }

    public function resolve(mixed $value): mixed { return $value; }
}
