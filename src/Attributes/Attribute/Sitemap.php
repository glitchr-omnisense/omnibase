<?php

namespace Base\Attributes\Attribute;

use Base\Attributes\AbstractAttribute;



/**
 * Class Sitemap
 * package Base\Attributes\Attribute\Sitemap
 *
 * @Attributes({
 *   @Attribute("group", type = "string"),
 *   @Attribute("priority", type = "float"),
 *   @Attribute("changefreq", type = "string"),
 *   @Attribute("lastmod", type = "string")
 * })
 */

 #[\Attribute(\Attribute::TARGET_METHOD)]
class Sitemap extends AbstractAttribute
{
    protected static array $urls = [];
    protected string $lastMod;
    protected string $changeFreq;
    protected float $priority;

    public function __construct(?string $group = null, float $priority = 0.5, string $changefreq = "daily", ?string $lastmod = null)
    {
        $this->group = $group;

        // date("Y-m-d H:m:s") put the MONTH where the minutes belong - "m" is
        // the month, "i" is the minutes - so every entry claimed to have been
        // modified at nine minutes past the hour, all year long. And the
        // sitemap spec wants W3C Datetime, which is ISO 8601: a space between
        // the date and the time is not it.
        $this->lastMod = $lastmod ?? date("c");
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
