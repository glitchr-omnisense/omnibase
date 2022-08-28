<?php

namespace Base\Entity\Layout\Attribute\Adapter;

use Base\Database\Annotation\DiscriminatorEntry;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\CountryAdapterRepository;

/**
 * @ORM\Entity(repositoryClass=CountryAdapterRepository::class)
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @DiscriminatorEntry( value = "country" )
 */

class CountryAdapter extends AbstractAdapter
{
    public static function __iconizeStatic() : ?array { return ["fas fa-flag"]; }

    public static function getType(): string { return CountryType::class; }
    public function getOptions(): array { return [
        //"alt" => ["label" => "Nom"]
    ]; }

    public function resolve(mixed $value): mixed { return $value; }
}
