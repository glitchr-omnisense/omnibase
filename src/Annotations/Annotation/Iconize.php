<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;

/**
 * Class Iconize
 * package Base\Annotations\Annotation\Iconize
 *
 * @Annotation
 * @Target({"METHOD"})
 */
class Iconize extends AbstractAnnotation
{
    protected array $icons;

    public function __construct(array $data)
    {
        $icons = $data["value"] ?? [];
        $this->icons = !is_array($icons) ? [$icons] : $icons;
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return true;
    }

    public function getIcons()
    {
        return $this->icons;
    }
}
