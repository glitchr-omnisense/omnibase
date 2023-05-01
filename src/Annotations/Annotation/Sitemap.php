<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;

/**
 * Class Sitemap
 * package Base\Annotations\Annotation\Sitemap
 *
 * @Annotation
 * @Target({"METHOD"})
 * @Attributes({
 *   @Attribute("group", type = "string"),
 *   @Attribute("priority", type = "float"),
 *   @Attribute("changefreq", type = "string"),
 *   @Attribute("lastmod", type = "string")
 * })
 */
class Sitemap extends AbstractAnnotation
{
    protected static array $urls = [];

    /**
     * @return array
     */
    public static function getUrls()
    {
        return self::$urls;
    }

    protected ?string $group = null;

    /**
     * @return mixed|string|null
     */
    public function getGroup()
    {
        return $this->group;
    }

    protected string $lastMod;

    /**
     * @return mixed|string
     */
    public function getLastMod()
    {
        return $this->lastMod;
    }

    protected string $changeFreq;

    /**
     * @return mixed|string
     */
    public function getChangeFreq()
    {
        return $this->changeFreq;
    }

    protected float $priority;

    /**
     * @return float|mixed
     */
    public function getPriority()
    {
        return $this->priority;
    }

    public function __construct(array $data)
    {
        $this->group = $data["group"] ?? null;

        $this->lastMod = $data["lastmod"] ?? date("Y-m-d H:m:s");
        $this->changeFreq = $data["changefreq"] ?? "daily";
        $this->priority = $data["priority"] ?? 0.5;
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
}
