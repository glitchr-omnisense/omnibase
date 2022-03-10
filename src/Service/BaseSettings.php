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

    public function all        (?string $locale = null) : array   { return $this->get(null, $locale); }
    public function mail       (?string $locale = null) : ?string { return $this->getScalar("base.settings.mail",      $locale); }
    public function mail_name  (?string $locale = null) : ?string { return $this->getScalar("base.settings.mail.name", $locale); }
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

    public function logo          (?string $locale = null) : ?string { return $this->getScalar("base.settings.logo",        $locale); }

    public function title      (?string $locale = null) : ?string { return $this->getScalar("base.settings.title",       $locale); }
    public function slogan     (?string $locale = null) : ?string { return $this->getScalar("base.settings.slogan",      $locale); }
    public function keywords   (?string $locale = null) : ?array  { return $this->getScalar("base.settings.keywords",    $locale); }
    public function author     (?string $locale = null) : ?string { return $this->getScalar("base.settings.author",    $locale); }
    public function description(?string $locale = null) : ?string { return $this->getScalar("base.settings.description", $locale); }
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
