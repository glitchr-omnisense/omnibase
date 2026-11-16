<?php

namespace Base\Mapping;

use Attribute;

/**
 * #[HasLifecycleCallbacks]: an entity whose methods carry lifecycle attributes (#[PrePersist]...).
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['HasLifecycleCallbacks'].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class HasLifecycleCallbacks implements MappingAttribute
{
}
