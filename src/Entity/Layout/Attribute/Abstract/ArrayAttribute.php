<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Field\Type\ArrayType;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Abstract\ArrayAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=ArrayAttributeRepository::class)
 * @DiscriminatorEntry( value = "array" )
 */

class ArrayAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __iconizeStatic() : ?array { return ["fas fa-clipboard-list"]; }
    
    public static function getType(): string { return ArrayType::class; }
    public function getOptions(): array { return []; }
    public function resolve(mixed $value): mixed { return $value; }
}
