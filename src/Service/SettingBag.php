<?php

namespace Base\Service;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Entity\Layout\Setting;
use Base\Traits\SettingBagTrait;
use Symfony\Component\Asset\Packages;

use DateTime;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;

class SettingBag implements SettingBagInterface
{
    use SettingBagTrait;

    /**
     * @var Packages
     */
    protected $packages;

    public function __construct(ClassMetadataManipulator $classMetadataManipulator, LocaleProviderInterface $localeProvider, Packages $packages, CacheInterface $cache, string $environment)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->entityManager            = $classMetadataManipulator->getEntityManager();
        $this->settingRepository        = $classMetadataManipulator->getRepository(Setting::class);

        $this->cache           = $cache;
        $this->cacheName       = "setting_bag." . hash('md5', self::class);
        $this->cacheSettingBag = !is_cli() ? $cache->getItem($this->cacheName) : null;

        $this->packages       = $packages;
        $this->localeProvider = $localeProvider;
        $this->environment    = $environment;
    }

    public function __call($name, $_) { return $this->get("base.settings.".$name); }
    public function maintenance(?string $locale = null) : bool    { return filter_var($this->getScalar("base.settings.maintenance",     $locale)); }

    public function all        (?string $locale = null) : array   { return $this->get(null, $locale); }
    public function scheme     (?string $locale = null) : string  { return filter_var($this->getScalar("base.settings.http.scheme",    $locale)) ? "https" : "http"; }
    public function base_dir   (?string $locale = null) : string  { return $this->getScalar("base.settings.http.base_dir", $locale) ?? "/"; }
    public function url(?string $path = null, ?string $packageName = null, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        if(str_starts_with($path, "http")) return $path;

        $absolutePath = $this->packages->getUrl($path ?? "", $packageName); // returning absolute path.. not real URL
        switch($referenceType) {

            case UrlGeneratorInterface::ABSOLUTE_URL:
                return str_rstrip($this->scheme()."://".$this->host().(str_starts_with($absolutePath, "/") ? "" : "/").$absolutePath, "/");
                break;

            case UrlGeneratorInterface::NETWORK_PATH:
                return "//".trim($absolutePath, "/");
                break;

            case UrlGeneratorInterface::RELATIVE_PATH:
                return $path;
                break;

            default:
            case UrlGeneratorInterface::ABSOLUTE_PATH:
                return $absolutePath;
        }
    }

    public function host     (int $level = 0, ?string $locale = null) : ?string
    {
        $host = $this->getScalar("base.settings.http.host", $locale) ?? null;
        while($level-- > 0) $host = preg_replace("/^(\w+)./i", "", $host);

        return $host;
    }

    public function birthdate(?string $locale = null) : DateTime
    {
        $birthdate = $this->getScalar("base.settings.birthdate", $locale);
        return $birthdate instanceof DateTime ? $birthdate : new DateTime($birthdate);
    }

    public function age(?string $locale = null) : string
    {
        $birthdate = $this->birthdate($locale)->format("Y");
        return (date("Y") <= $birthdate) ? date("Y") : date("$birthdate-Y");
    }
}
