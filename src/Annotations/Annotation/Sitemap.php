<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;

/**
 * Class Timezone
 * package Base\Annotations\Annotation\Timezone
 *
 * @Annotation
 * @Target({"METHOD"})
 * @Attributes({
 * 
 * })
 */
class Sitemap extends AbstractAnnotation
{
    protected static array $loc = [];
    public static function getStaticLoc() { return self::$loc; }

    public function __construct(array $data)
    {
        dump("SITEMAP !");
        dump($data);
    }

    public function supports($classMetadata, string $target, ?string $targetValue = null, $entity = null): bool
    {
        dump($target, $targetValue, $entity);
        dump($this->getAnnotations($entity, $target, $targetValue));

        return true;
    }
}
