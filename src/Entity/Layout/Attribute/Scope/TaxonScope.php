<?php

namespace Base\Entity\Layout\Attribute\Scope;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\AbstractAdapter;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Attribute\Scope\TaxonScopeRepository;
use Base\Entity\Thread\Taxon;
use Base\Field\Type\SelectType;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=TaxonScopeRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry( value = "scope_taxon" )
 */

class TaxonScope extends AbstractAdapter
{
    public static function __iconizeStatic() : ?array { return Taxon::__iconizeStatic(); }

    public static function getType(): string { return SelectType::class; }
    public function getOptions(): array { return []; }

    public function resolve(mixed $value): mixed { return $value; }
}
