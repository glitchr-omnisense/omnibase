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

    public function getScalar($name, ?string $locale = null)
    {
        if(is_array($names = $name)) {
            
            $settings = [];
            foreach($names as $name)
                $settings[] = $this->getScalar($name, $locale);

            return $settings;
        }

        return $this->settings[$name] 
            ?? $this->settingRepository->findOneByInsensitiveName($name);
    }

    public function getRaw($name, ?string $locale = null)
    {
        if(is_array($names = $name)) {
            
            $settings = [];
            foreach($names as $name)
                $settings[] = $this->getRaw($name, $locale);

            return $settings;
        }

        return $this->settings[$name] 
            ?? $this->settingRepository->findByInsensitiveNameStartingWith($name)->getResult();
    }

    public function get($name, ?string $locale = null)
    {
        if(!$locale)
            $locale = $this->localeProvider->getLocale($locale);

        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        if(is_array($names = $name)) {
        
            $settings = [];
            foreach($names as $name)
                $settings[] = $this->get($name, $locale);

            return $settings;
        }

        $item = $this->cache->getItem($name);
        $itemList = $item->get() ?? [];
        if (array_key_exists($locale, $itemList))
            return $itemList[$locale][""] ?? "";

        $this->settings[$name] = $this->getRaw($name);
        if(!$this->settings[$name]) 
            return null;

        // Simplest case: scalar value
        if(!is_array($this->settings[$name])) {

            $value = $this->settings[$name]->translate($locale)->getValue();
            $this->applyCache($name, $locale, $value);

            return $value;
        } 
        
        // Formatted structure.. from dot to array
        $value = [];
        foreach($this->settings[$name] as $setting) {

            $fullName = $setting->getName();
            $partName = substr($fullName, strlen($name)+1, strlen($fullName));
            
            $array = &$value;
            foreach (explode(".", $partName) as $key) {

                if(!array_key_exists($key,$array)) $array[$key] = [];
                else if(!is_array($array[$key])) $array[$key] = ["" => $array[$key] ?? ""];

                $array = &$array[$key];
            }

            $array = $setting;
        }

        $this->applyCache($name, $locale, $value);
        return $value[""] ?? "";
    }

    
    public function set(string $name, string $value, ?string $locale = null)
    {
        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        $this->removeCache($name);

        $this->settings[$name] = $this->getRaw($name);
        if($this->settings[$name] instanceof Setting) {

            $this->settings[$name]->translate($locale)->setValue($value);
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

    protected function applyCache(string $name, ?string $locale, $value)
    {
        if(!$locale)
            $locale = $this->localeProvider->getLocale($locale);

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
    
    protected function removeCache(string $name)
    {
        foreach(array_reverse(explode(".", $name)) as $last) {
        
            $this->cache->delete($name);
            $name = substr($name, 0, strlen($name) - strlen($last));
        }

        return $this;
    }
}