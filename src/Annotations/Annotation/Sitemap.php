<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Attribute;
use Doctrine\Common\Annotations\Annotation\Attributes;
use Doctrine\Common\Annotations\Annotation\Target;

use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Class Sitemap
 * package Base\Annotations\Annotation\Sitemap
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"METHOD"})
 * @Attributes({
 *   @Attribute("group", type = "string"),
 *   @Attribute("priority", type = "float"),
 *   @Attribute("changefreq", type = "string"),
 *   @Attribute("lastmod", type = "string")
 * })
 */

 #[\Attribute(\Attribute::TARGET_METHOD)]
class Sitemap extends AbstractAnnotation
{
    protected static array $urls = [];
    protected string $lastMod;
    protected string $changeFreq;
    protected float $priority;

    public function __construct(?string $group = null, float $priority = 0.5, string $changefreq = "daily", ?string $lastmod = null)
    {
        $this->group = $group;

        $this->lastMod = $lastmod ?? date("Y-m-d H:m:s");
        $this->changeFreq = $changefreq;
        $this->priority = $priority;
    }

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

    /**
     * @return mixed|string
     */
    public function getLastMod()
    {
        return $this->lastMod;
    }

    /**
     * @return mixed|string
     */
    public function getChangeFreq()
    {
        return $this->changeFreq;
    }

    /**
     * @return float|mixed
     */
    public function getPriority()
    {
        return $this->priority;
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
