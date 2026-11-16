<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[DiscriminatorMap]: the classes of an inheritance by their discriminator value.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['DiscriminatorMap'].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class DiscriminatorMap implements MappingAttribute
{
    /**
     * @param array<int|string, string> $value
     */
    public function __construct(
        public readonly array $value,
    ) {
    }
}
