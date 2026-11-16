<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[ChangeTrackingPolicy]: how the changes of an entity are found (DEFERRED_IMPLICIT, DEFERRED_EXPLICIT).
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['ChangeTrackingPolicy'].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ChangeTrackingPolicy implements MappingAttribute
{
    public function __construct(
        public readonly string $value,
    ) {
    }
}
