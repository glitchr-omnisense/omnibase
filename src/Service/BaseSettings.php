<?php

namespace Base\Service;

use Base\Entity\Sitemap\Setting;
use Base\Repository\Sitemap\SettingRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

use Base\Service\Traits\BaseSettingsTrait;
use Symfony\Component\Asset\Packages;

class BaseSettings
{
    use BaseSettingsTrait;

    public function __construct(SettingRepository $settingRepository, Packages $packages)
    {
        $this->settingRepository = $settingRepository;
        $this->packages = $packages;
    }

    public function protocol   () { return $this->get("app.settings.use_https"  ); }
    public function maintenance() { return $this->get("app.settings.maintenance"); }

    public function logo       (?string $locale = null) { return $this->get("app.settings.logo",         $locale); }
    public function title      (?string $locale = null) { return $this->get("app.settings.title",        $locale); }
    public function slogan     (?string $locale = null) { return $this->get("app.settings.slogan",       $locale); }
    public function contact    (?string $locale = null) { return $this->get("app.settings.contact",      $locale); }
    public function contactName(?string $locale = null) { return $this->get("app.settings.contact_name", $locale); }
    public function birthdate  (?string $locale = null) { return $this->get("app.settings.birthdate",    $locale); }    

    public function age(?string $locale = null) { return $this->getAge($locale); }
    public function getAge     (?string $locale = null)
    { 
        $birthdate = $this->birthdate($locale);
        return (date("Y") == $birthdate) ? date("Y") : date("$birthdate-Y");
    }

    public function url($url, $packages = null) { return $this->getUrl($url, $packages); }
    public function getUrl($url, $packages = null) 
    { 
        return $this->packages->getUrl($url, $packages);
    }

    public function domain     (int $level = 0, ?string $locale = null) : string { return $this->getDomain($level, $locale); }
    public function getDomain(int $level = 0, ?string $locale = null)  : string 
    {
        $domain = $this->get("app.settings.domain",       $locale);
        while($level-- > 0)
            $domain = preg_replace("/^(\w+)./i", "", $domain);

        return $domain;
    }
}