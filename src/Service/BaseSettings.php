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

    public function protocol   () { return filter_var($this->get("base.settings.use_https"  )) ? "https" : "http"; }
    public function maintenance() { return $this->get("base.settings.maintenance"); }

    public function logo     (?string $locale = null) { return $this->get("base.settings.logo",      $locale); }
    public function title    (?string $locale = null) { return $this->get("base.settings.title",     $locale); }
    public function slogan   (?string $locale = null) { return $this->get("base.settings.slogan",    $locale); }
    public function mail     (?string $locale = null) { return $this->get("base.settings.mail",      $locale); }
    public function mailName (?string $locale = null) { return $this->get("base.settings.mail_name", $locale); }
    public function birthdate(?string $locale = null) : \DateTime { return new \DateTime($this->get("base.settings.birthdate", $locale)); }

    public function age(?string $locale = null) { return $this->getAge($locale); }
    public function getAge     (?string $locale = null)
    { 
        $birthdate = $this->birthdate($locale)->format("Y");
        return (date("Y") <= $birthdate) ? date("Y") : date("$birthdate-Y");
    }

    public function url($url, $packages = null) { return $this->getUrl($url, $packages); }
    public function getUrl($url, $packages = null)
    {
        return $this->packages->getUrl($url, $packages);
    }

    public function domain     (int $level = 0, ?string $locale = null) { return $this->getDomain($level, $locale); }
    public function getDomain(int $level = 0, ?string $locale = null)
    {
        $domain = $this->get("base.settings.domain",       $locale);
        while($level-- > 0)
            $domain = preg_replace("/^(\w+)./i", "", $domain);

        return $domain;
    }
}
