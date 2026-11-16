<?php

namespace Base\Mapping;

/**
 * #[JoinColumns]: several join columns (inside another attribute).
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['JoinColumns'].
 */
final class JoinColumns implements MappingAttribute
{
    /**
     * @param array<JoinColumn> $value
     */
    public function __construct(
        public readonly array $value,
    ) {
    }
}
