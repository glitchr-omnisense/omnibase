<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Attributes\AttributeReader;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;
use Base\Database\Schema\DiscriminatorRule;

/**
 * The value a class of an inheritance is written under in the discriminator column: the one given, else one derived
 * from the class's name and its parent's entry (DiscriminatorRule::value()). ONE implementation, on any persistence:
 * the description's map (DiscriminatorRule, read by Eloquent and by SchemaCreator) and Doctrine's (its bridge hands
 * each class of the map to DiscriminatorRule::value()) carry the same values.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class DiscriminatorEntry extends AbstractAttribute implements ExtensionOptionInterface
{
    /** @Required */
    protected ?string $value;

    public function __construct(?string $value = null)
    {
        $this->value = $value ?? null;
    }

    public function getValue(object|string $object_or_class): string
    {
        return $this->value ?? DiscriminatorRule::value(is_object($object_or_class) ? get_class($object_or_class) : $object_or_class);
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return AttributeReader::TARGET_CLASS === $target;
    }
}
