<?php

namespace Base\Service\Traits;

use Base\Entity\Sitemap\Setting;
use Base\Service\BaseService;
use Base\Service\LocaleProvider;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\Cache\CacheInterface;

trait BaseSettingsTrait
{
    protected $cache = null;

    protected $settingRepository = null;
    protected $settings = [];

    public function getSettings($name = null)
    {
        if($name) {
        
            if(is_array($name)) {

                $settings = [];
                foreach($name as $entry)
                    $settings[$entry] = $this->settings[$entry] ?? $this->settingRepository->findOneByName($entry) ?? null;
                
                return $settings;
            }
        
            return $this->settings[$name] ?? $this->settingRepository->findOneByName($name) ?? null;
        }

        return $this->all();
    }

    private $allFlags = false;
    public function all(?string $locale = null)
    {
        if(!$locale)
            $locale = $this->localeProvider->getLocale($locale);

        if($this->allFlags) return $this->settings;
        foreach($this->settingRepository->findAll() as $setting)
        {
            $name = $setting->getName();
            $this->settings[$name] = $setting;
            $this->applyCache($name, $locale, $this->settings[$name]->translate($locale)->getValue());
        }

        $this->allFlags = true;
        return $this->settings;
    }

    public function has(string $name, ?string $locale = null)
    {
        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        return $this->get($name, $locale) !== null;
    }

    public function getHelp(string $name, ?string $locale = null)
    {
        $helpName = $name.":help";
        if(!$locale)
            $locale = $this->localeProvider->getLocale($locale);

        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        $item = $this->cache->getItem($helpName);
        if (array_key_exists($locale, $item->get() ?? []))
            return $item->get()[$locale];

        $this->settings[$name] = $this->getSettings($name);
        if(!$this->settings[$name]) 
            return null;

        $value  = $this->settings[$name]->translate($locale)->getHelp();
        $this->applyCache($helpName, $locale, $value);

        return $value;
    }

    public function getLabel(string $name, ?string $locale = null)
    {
        $labelName = $name.":label";
        if(!$locale)
            $locale = $this->localeProvider->getLocale($locale);

        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        $item = $this->cache->getItem($labelName);
        if (array_key_exists($locale, $item->get() ?? []))
            return $item->get()[$locale];

        $this->settings[$name] = $this->getSettings($name);
        if(!$this->settings[$name]) 
            return null;

        $value  = $this->settings[$name]->translate($locale)->getLabel();
        $this->applyCache($labelName, $locale, $value);

        return $value;
    }
    
    public function get(string $name, ?string $locale = null)
    {
        if(!$locale)
            $locale = $this->localeProvider->getLocale($locale);

        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        $item = $this->cache->getItem($name);
        if (array_key_exists($locale, $item->get() ?? []))
            return $item->get()[$locale];

        $this->settings[$name] = $this->getSettings($name);
        if(!$this->settings[$name]) 
            return null;

        $value  = $this->settings[$name]->translate($locale)->getValue();
        $this->applyCache($name, $locale, $value);

        return $value;
    }

    public function set(string $name, string $value, ?string $locale = null)
    {
        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        $this->removeCache($name);

        $this->settings[$name] = $this->getSettings($name);
        if($this->settings[$name]) {

            $this->settings[$name]->translate($locale)->setValue($value);
            $this->settingRepository->flush();
        }

        return $this;
    }

    public function remove(string $name)
    {
        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        $this->removeCache($name);
        $this->removeCache($name.":label");
        $this->removeCache($name.":help");

        $this->settings[$name] = $this->settingRepository->findOneByName($name);
        if($this->settings[$name])
            unset($this->settings[$name]);

        return $this;
    }

    public function applyCache(string $name, ?string $locale, $value)
    {
        if(!$locale)
            $locale = $this->localeProvider->getLocale($locale);

        $item = $this->cache->getItem($name);
        $values = array_merge($item->get() ?? [], [$locale => $value]);
        $this->cache->save($item->set($values));
    }
    
    public function removeCache(string $name)
    {
        $this->cache->delete($name);

        return $this;
    }
}