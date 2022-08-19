<?php

namespace Base\Entity\Layout\Attribute\Abstract;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Abstract\CountryAttributeRepository;

/**
 * @ORM\Entity(repositoryClass=CountryAttributeRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @DiscriminatorEntry( value = "country" )
 */

class CountryAttribute extends AbstractAttribute implements IconizeInterface
{
    public static function __iconizeStatic() : ?array { return ["fas fa-flag"]; }

    public static function getType(): string { return CountryType::class; }
    public function getOptions(): array { return [
        //"alt" => ["label" => "Nom"]
    ]; }

    public function resolve(mixed $value): mixed { return $value; }
}
