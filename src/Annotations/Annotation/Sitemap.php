<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Class Sitemap
 * package Base\Annotations\Annotation\Sitemap
 *
 * @Annotation
 * @Target({"METHOD"})
 * @Attributes({
 *   @Attribute("priority", type = "float"),
 *   @Attribute("changefreq", type = "string"),
 *   @Attribute("lastmod", type = "string")
 * })
 */
class Sitemap extends AbstractAnnotation
{
    protected static array $urls = [];
    public static function getUrls() { return self::$urls; }

    protected string $lastMod;
    protected string $changeFreq;
    protected float  $priority;
    
    public function getPriority  () { return $this->priority;   }
    public function getChangeFreq() { return $this->changeFreq; }
    public function getLastMod   () { return $this->lastMod;    }

    public function __construct(array $data)
    {
        $this->lastMod = $data["lastmod"] ?? date("Y-m-d H:m:s");
        $this->changeFreq = $data["changefreq"] ?? "daily";
        $this->priority = $data["priority"] ?? 0.5;
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return true;
    }
}
