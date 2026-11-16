<?php

namespace Base\Database\Attribute;

use Base\Attributes\AbstractAttribute;
use Base\Database\Entity\EntityExtension;
use Base\Database\Attribute\Extension\ExtensionOptionInterface;


/**
 * @Attributes({
 *   @Attribute("column" , type = "string"),
 *   @Attribute("value" , type = "bool")
 * })
 */

/** An association's orphan removal: Cascade::resolve() reads it, on every persistence. */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY)]
class OrphanRemoval extends AbstractAttribute implements ExtensionOptionInterface
{
    /** @Required */
    protected string $column;
    protected bool $value;

    public function __construct(string $column = "", bool $value = true)
    {
        $this->column = $column;
        $this->value = $value;
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == EntityExtension::TARGET_CLASS || $target == EntityExtension::TARGET_PROPERTY);
    }

}
