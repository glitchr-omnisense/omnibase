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
    protected string $src;

    public function __construct(array $data) { $this->src = $data["value"] ?? ""; }
    public function supports(string $target, ?string $targetValue = null, $object = null): bool { return true; }

    public function getIcon() { return $this->src; }
}
