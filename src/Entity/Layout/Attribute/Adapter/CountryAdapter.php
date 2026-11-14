<?php

namespace Base\Entity\Layout\Attribute\Adapter;

use Base\Database\Attribute\DiscriminatorEntry;

use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;
use Doctrine\ORM\Mapping as ORM;
use Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\CountryAdapterRepository;
use Base\Database\Attribute\Cache;
use Omnibridge\Symfony\Omnibase\Field\Type\CountryType;

#[ORM\Entity(repositoryClass: CountryAdapterRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry(value: "country" )]
class CountryAdapter extends AbstractAdapter
{
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-flag"];
    }

    public static function getType(): string
    {
        return CountryType::class;
    }
    public function getOptions(): array
    {
        return [
            //"alt" => ["label" => "Nom"]
        ];
    }

    public function resolve(mixed $value): mixed
    {
        return $value;
    }
}
