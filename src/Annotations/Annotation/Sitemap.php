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
 *   @Attribute("priority", type = "float"),
 * })
 */
class Sitemap extends AbstractAnnotation
{
    protected static array $urls = [];
    public static function getUrls() { return self::$urls; }

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
