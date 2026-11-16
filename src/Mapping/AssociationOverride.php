<?php

namespace Base\Mapping;

/**
 * #[AssociationOverride]: an association of a mapped superclass redeclared by an entity (inside #[AssociationOverrides]).
 *
 * omnibase's mapping vocabulary (Base\Mapping), read by Omnibridge\Schema\Declaration\
 * AttributeReader for every persistence (Doctrine, Eloquent) from its arguments as written:
 * the parameters, their order and defaults of Vocabulary::PARAMETERS['AssociationOverride'].
 */
final class AssociationOverride implements MappingAttribute
{
    /** @var array<JoinColumn>|null */
    public readonly array|null $joinColumns;
    /** @var array<JoinColumn>|null */
    public readonly array|null $inverseJoinColumns;

    /**
     * @param array<JoinColumn>|JoinColumn|null $joinColumns
     * @param array<JoinColumn>|JoinColumn|null $inverseJoinColumns
     */
    public function __construct(
        public readonly string $name,
        array|JoinColumn|null $joinColumns = null,
        array|JoinColumn|null $inverseJoinColumns = null,
        public readonly JoinTable|null $joinTable = null,
        public readonly string|null $inversedBy = null,
        public readonly string|null $fetch = null,
    ) {
        if ($joinColumns instanceof JoinColumn) {
            $joinColumns = [$joinColumns];
        }

        if ($inverseJoinColumns instanceof JoinColumn) {
            $inverseJoinColumns = [$inverseJoinColumns];
        }

        $this->joinColumns = $joinColumns;
        $this->inverseJoinColumns = $inverseJoinColumns;
    }
}
