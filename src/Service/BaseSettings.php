<?php

namespace Base\Service;

use Base\Repository\Sitemap\SettingRepository;

use Base\Service\Traits\BaseSettingsTrait;
use Symfony\Component\Asset\Packages;
use Symfony\Contracts\Cache\CacheInterface;

class BaseSettings
{
    use BaseSettingsTrait;

    public function __construct(SettingRepository $settingRepository, LocaleProviderInterface $localeProvider, Packages $packages, CacheInterface $cache)
    {
        $this->settingRepository = $settingRepository;
        $this->packages = $packages;
        $this->cache   = $cache;
        $this->localeProvider = $localeProvider;
    }

    public function mail       (?string $locale = null) : ?string { return $this->getScalar("base.settings.mail",      $locale); }
    public function mail_name  (?string $locale = null) : ?string { return $this->getScalar("base.settings.mail.name", $locale); }
    public function protocol   (?string $locale = null) : string  { return filter_var($this->getScalar("base.settings.domain.https", $locale)) ? "https" : "http"; }
    public function maintenance(?string $locale = null) : bool    { return filter_var($this->getScalar("base.settings.maintenance", $locale)); }
    public function url($url, $packages = null) { return $this->packages->getUrl($url, $packages); }
    public function domain     (int $level = 0, ?string $locale = null) : ?string
    {
        $domain = $this->getScalar("base.settings.domain",       $locale);
        while($level-- > 0)
            $domain = preg_replace("/^(\w+)./i", "", $domain);

        return $domain;
    }

    public function logo     (?string $locale = null) : ?string   { return $this->getScalar("base.settings.logo",      $locale); }
    public function title    (?string $locale = null) : ?string   { return $this->getScalar("base.settings.title",     $locale); }
    public function slogan   (?string $locale = null) : ?string   { return $this->getScalar("base.settings.slogan",    $locale); }
    public function birthdate(?string $locale = null) : \DateTime { return new \DateTime($this->getScalar("base.settings.birthdate", $locale) ?? "now"); }
    public function age(?string $locale = null) : string {

        $birthdate = $this->birthdate($locale)->format("Y");
        return (date("Y") <= $birthdate) ? date("Y") : date("$birthdate-Y");
    }

}
