<?php

namespace Base\Service;

use Base\Entity\Sitemap\Setting;
use Base\Repository\Sitemap\SettingRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

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

    public function mail       (?string $locale = null) : string { return $this->get("base.settings.mail",      $locale); }
    public function mail_name  (?string $locale = null) : string { return $this->get("base.settings.mail.name", $locale); }
    public function protocol   (?string $locale = null) : string { return filter_var($this->get("base.settings.domain.https", $locale)) ? "https" : "http"; }
    public function maintenance(?string $locale = null) : bool   { return filter_var($this->get("base.settings.maintenance", $locale)); }
    public function domain     (int $level = 0, ?string $locale = null) : string
    {
        $domain = $this->get("base.settings.domain",       $locale);
        while($level-- > 0)
            $domain = preg_replace("/^(\w+)./i", "", $domain);

        return $domain;
    }

    public function logo     (?string $locale = null) : string    { return $this->get("base.settings.logo",      $locale); }
    public function title    (?string $locale = null) : string    { return $this->get("base.settings.title",     $locale); }
    public function slogan   (?string $locale = null) : string    { return $this->get("base.settings.slogan",    $locale); }
    public function birthdate(?string $locale = null) : \DateTime { return new \DateTime($this->get("base.settings.birthdate", $locale)); }
    public function age(?string $locale = null) : string {

        $birthdate = $this->birthdate($locale)->format("Y");
        return (date("Y") <= $birthdate) ? date("Y") : date("$birthdate-Y");
    }

}
