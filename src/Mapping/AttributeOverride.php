<?php

namespace Base\Mapping;

/**
 * #[AttributeOverride]: a column of a mapped superclass redeclared by an entity (inside #[AttributeOverrides]).
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['AttributeOverride'].
 */
final class AttributeOverride implements MappingAttribute
{
    public function __construct(
        public string $name,
        public Column $column,
    ) {
    }
}
