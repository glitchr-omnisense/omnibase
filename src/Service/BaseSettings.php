<?php

namespace Base\Service;

use Base\Database\Factory\ClassMetadataManipulator;
use Base\Entity\Layout\Setting;
use Base\Traits\BaseSettingsTrait;
use Symfony\Component\Asset\Packages;

use DateTime;

class BaseSettings
{
    use BaseSettingsTrait;

    public function __construct(ClassMetadataManipulator $classMetadataManipulator, LocaleProviderInterface $localeProvider, Packages $packages, string $environment)
    {
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->entityManager            = $classMetadataManipulator->getEntityManager();
        $this->settingRepository        = $classMetadataManipulator->getRepository(Setting::class);

        $this->packages       = $packages;
        $this->localeProvider = $localeProvider;
        $this->environment    = $environment;
    }

    public function __call($name, $arguments) { return $this->get("base.settings.".$name); }
    public function all        (?string $locale = null) : array   { return $this->get(null, $locale); }
    public function scheme     (?string $locale = null) : string  { return filter_var($this->getScalar("base.settings.domain.scheme",    $locale)) ? "https" : "http"; }
    public function maintenance(?string $locale = null) : bool    { return filter_var($this->getScalar("base.settings.maintenance",     $locale)); }
    public function base_dir   (?string $locale = null) : string  { return $this->getScalar("base.settings.domain.base_dir", $locale) ?? "/"; }
    public function url($url, $packages = null) { return $this->packages->getUrl($url, $packages); }
    public function domain     (int $level = 0, ?string $locale = null) : ?string
    {
        $domain = $this->getScalar("base.settings.domain", $locale);
        while($level-- > 0) $domain = preg_replace("/^(\w+)./i", "", $domain);

        return $domain;
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
