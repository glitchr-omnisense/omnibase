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

    public function normalize($name, array $settings) {

        $values = [];

        // Default structure
        $array = &$values;
        foreach (explode(".", $name) as $key) {
            if(!array_key_exists($key,$array)) $array[$key] = ["" => null];
            $array = &$array[$key];
        }

        // Fill it with settings
        foreach($settings as $setting) {

            $array = &$values;
            foreach (explode(".", $setting->getName()) as $key) {
                $array = &$array[$key];
            }
            
            $array[""] = $setting;
        }

        return $values;
    }
    
    public function getRaw($name, ?string $locale = null)
    {
        if(!$locale)
            $locale = $this->localeProvider->getLocale($locale);

        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        if(is_array($names = $name)) {
            
            $settings = [];
            foreach($names as $name)
                $settings[] = $this->getRaw($name, $locale);

            return $settings;
        }

        $this->settings[$name] = $this->settings[$name] 
            ?? $this->settingRepository->findByInsensitiveNameStartingWith($name)->getResult();

        $values = $this->normalize($name, $this->settings[$name]);
        $this->applyCache($name, $locale, $values);

        return $values;
    }
    
    public function getRawScalar($name, ?string $locale = null)
    {
        if(!$locale)
            $locale = $this->localeProvider->getLocale($locale);

        if(is_array($names = $name)) {
            
            $settings = [];
            foreach($names as $name)
                $settings[] = $this->getScalar($name, $locale);

            return $settings;
        }

        $array = $this->getRaw($name, $locale);
        foreach (explode(".", $name) as $key) {
            if(!array_key_exists($key,$array)) $array[$key] = ["" => null];
            $array = &$array[$key];
        }

        return $array[""] ?? null;
    }

    public function getScalar($name, ?string $locale = null)
    {
        if(!$locale)
            $locale = $this->localeProvider->getLocale($locale);

        if(is_array($names = $name)) {
            
            $settings = [];
            foreach($names as $name)
                $settings[] = $this->getScalar($name, $locale);

            return $settings;
        }

        if(($cacheValues = $this->getCache($name, $locale)))
            return ($cacheValues !== null ? $cacheValues[""] ?? null : null);

        $array = $this->getRawScalar($name, $locale);
        return ($array ? $array->translate($locale)->getValue() ?? null : "");
    }

    public function get($name, ?string $locale = null): array
    {
        if(!$locale)
            $locale = $this->localeProvider->getLocale($locale);

        if(is_array($names = $name)) {
        
            $settings = [];
            foreach($names as $name)
                $settings[] = $this->get($name, $locale);

            return $settings;
        }

        if(($cacheValues = $this->getCache($name, $locale)))
            return $cacheValues;

        return $this->getRaw($name, $locale) ?? [];
    }

    
    public function set(string $name, string $value, ?string $locale = null)
    {
        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        $this->removeCache($name);

        $setting = $this->getRaw($name)[""] ?? null;
        if($setting instanceof Setting) {

            $setting->translate($locale)->setValue($value);
            $this->settingRepository->flush();
        }

        return $this;
    }

    public function has(string $name, ?string $locale = null)
    {
        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        return $this->get($name, $locale) !== null;
    }
    
    public function remove(string $name)
    {
        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        $this->removeCache($name);

        $this->settings[$name] = $this->settingRepository->findOneByInsensitiveName($name);
        if($this->settings[$name] instanceof Setting)
            unset($this->settings[$name]);

        return $this;
    }

    protected function getCache(string $name, string $locale) 
    {
        $item = $this->cache->getItem($name);
        $itemList = $item->get() ?? [];
        if (array_key_exists($locale, $itemList))
            return $itemList[$locale] ?? [];

        return null;
    }

    protected function applyCache(string $name, string $locale, $value)
    {
        if(!$value) return false;
        
        if(($setting = $value) instanceof Setting) 
            return $this->applyCache($name, $locale, $setting->translate($locale)->getValue());

        if(is_array( ($values = $value) )) {

            if(array_key_exists("", $values) && ($setting = $values[""]) instanceof Setting)
                $values[""] = $setting->translate($locale)->getValue();

            $item = $this->cache->getItem($name);
            $localeValues = array_merge($item->get() ?? [], [$locale => $values]);
            foreach($localeValues as $locale => $values) {

                // Broadcast cache storage
                foreach  ($values as $subname => $value) {

                    if(empty($subname)) continue;
                    $this->applyCache($name.".".$subname, $locale, $value);
                }

                // Process current node
                $localeValues[$locale] = BaseService::array_map_recursive(function($value) use ($locale) {
                    return ($value instanceof Setting ? $value->translate($locale)->getValue() : $value);
                }, $values);
            }

            $this->cache->save($item->set($localeValues));
            return true;
        }

        $item = $this->cache->getItem($name);
        $values = array_merge($item->get() ?? [], [$locale => $value]);
        $this->cache->save($item->set($values));

        return true;
    }
    
    public function removeCache(string $name)
    {
        foreach(array_reverse(explode(".", $name)) as $last) {
        
            $this->cache->delete($name);
            $name = substr($name, 0, strlen($name) - strlen($last));
        }

        return $this;
    }
}