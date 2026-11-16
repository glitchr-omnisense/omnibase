<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[EntityListeners]: the listener classes of an entity's lifecycle.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['EntityListeners'].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class EntityListeners implements MappingAttribute
{
    /**
     * @param array<string> $value
     */
    public function __construct(
        public readonly array $value = [],
    ) {
    }
}
