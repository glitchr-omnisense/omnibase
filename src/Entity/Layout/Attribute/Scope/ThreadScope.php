<?php

namespace Base\Entity\Attribute\Scope;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\AbstractAdapter;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Attribute\Scope\ThreadScopeRepository;
use Base\Entity\Thread;
use Base\Field\Type\SelectType;

/**
 * @ORM\Entity(repositoryClass=ThreadScopeRepository::class)
 * @DiscriminatorEntry( value = "scope_thread" )
 */

class ThreadScope extends AbstractAdapter
{
    public static function __iconizeStatic() : ?array { return Thread::__iconizeStatic(); }

    public static function getType(): string { return SelectType::class; }
    public function getOptions(): array { return []; }

    public function resolve(mixed $value): mixed { return $value; }
}
