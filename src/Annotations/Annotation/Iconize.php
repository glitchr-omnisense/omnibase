<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Class Iconize
 * package Base\Metadata\Extension\Iconize
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"METHOD"})
 */

 #[\Attribute(\Attribute::TARGET_METHOD)]
class Iconize extends AbstractAnnotation
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
