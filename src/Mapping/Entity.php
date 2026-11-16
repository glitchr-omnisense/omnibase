<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[Entity]: a class stored in a table of its own (its repository: repositoryClass).
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['Entity'].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Entity implements MappingAttribute
{
    /** @param class-string|null $repositoryClass the repository of its records (Base\Repository\..., App\Repository\...) */
    public function __construct(
        public readonly string|null $repositoryClass = null,
        public readonly bool $readOnly = false,
    ) {
    }
}
