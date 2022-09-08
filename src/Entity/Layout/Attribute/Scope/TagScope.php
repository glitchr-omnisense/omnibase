<?php

namespace Base\Entity\Layout\Attribute\Scope;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\AbstractAdapter;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Attribute\Scope\TagScopeRepository;
use Base\Entity\Thread\Tag;
use Base\Field\Type\SelectType;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=TagScopeRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry( value = "scope_tag" )
 */

class TagScope extends AbstractAdapter
{
    public static function __iconizeStatic() : ?array { return Tag::__iconizeStatic(); }

    public static function getType(): string { return SelectType::class; }
    public function getOptions(): array { return []; }

    public function resolve(mixed $value): mixed { return $value; }
}
