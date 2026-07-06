<?php

namespace Base\Attributes\Attribute;

use Base\Attributes\AbstractAttribute;


/**
 * Class Iconize
 * package Base\Attributes\Attribute\Iconize
 */

 #[\Attribute(\Attribute::TARGET_METHOD)]
class Iconize extends AbstractAttribute
{
    protected array $icons;

    public function __construct(array|string $icons = [])
    {
        $icons = $icons;
        $this->icons = !is_array($icons) ? [$icons] : $icons;
    }

    /**
     * @param string $target
     * @param string|null $targetValue
     * @param $object
     * @return bool
     */
    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return true;
    }

    /**
     * @return array
     */
    public function getIcons()
    {
        return $this->icons;
    }
}
