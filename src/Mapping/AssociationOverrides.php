<?php

namespace Base\Mapping;

use Attribute;
use function array_values;
use function get_debug_type;
use function is_array;
use function sprintf;

/**
 * #[AssociationOverrides]: the associations of a mapped superclass an entity redeclares.
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['AssociationOverrides'].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AssociationOverrides implements MappingAttribute
{
    public readonly array $overrides;

    public function __construct(array|AssociationOverride $overrides)
    {
        if (!is_array($overrides)) {
            $overrides = [$overrides];
        }

        foreach ($overrides as $override) {
            if (!($override instanceof AssociationOverride)) {
                throw new \InvalidArgumentException(sprintf('An #[AssociationOverrides] holds #[AssociationOverride]s, not %s.', get_debug_type($override)));
            }
        }

        $this->overrides = array_values($overrides);
    }
}
