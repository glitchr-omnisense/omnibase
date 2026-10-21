<?php

namespace Base\Database\Schema;

use Omnibridge\Schema\Builder;
use Omnibridge\Schema\Builder\Draft;
use Omnibridge\Schema\Builder\Rule;
use Omnibridge\Schema\Declaration\ClassDeclaration;

/**
 * The targets resolved, as Doctrine's ResolveTargetEntityListener resolves
 * them (DoctrineBundle's resolve_target_entities, Mapping::register()'s
 * option): a relation toward an interface points to the class the
 * application gave for it, the rest of the relation as it was.
 */
final class TargetRule implements Rule
{
    /** @param array<string, class-string> $targets interface => class */
    public function __construct(private readonly array $targets = [])
    {
    }

    public function before(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
    }

    public function declared(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
    }

    public function loaded(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
        foreach ($class->associationMappings as $property => $mapping) {
            if (isset($this->targets[$mapping['targetEntity']])) {
                // Doctrine's listener maps the relation again: it moves to the end of the relations.
                unset($class->associationMappings[$property]);
                $mapping['targetEntity'] = ltrim($this->targets[$mapping['targetEntity']], '\\');
                $class->associationMappings[$property] = $mapping;
            }
        }
    }
}
