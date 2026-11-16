<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[MappedSuperclass]: a parent class whose mapping its entities inherit, with no table of its own.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['MappedSuperclass'].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class MappedSuperclass implements MappingAttribute
{
    /** @param class-string|null $repositoryClass the repository of its records (Base\Repository\..., App\Repository\...) */
    public function __construct(
        public readonly string|null $repositoryClass = null,
    ) {
    }
}
