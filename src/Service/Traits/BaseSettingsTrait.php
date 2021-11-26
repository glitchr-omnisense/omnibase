<?php

namespace Base\Service\Traits;

use Base\Entity\Sitemap\Setting;
use Base\Service\BaseService;

use Doctrine\DBAL\Exception\TableNotFoundException;

trait BaseSettingsTrait
{
    protected $cacheEnabled = true; /* FOR DEVELOPMENT: FORCE DISABLING CACHE */
    protected $cache = null;

    protected $settingRepository = null;
    protected $settings = [];

    protected function read(string $name, array $normSettings)
    {
        $nameArray = explode(".", $name);
        foreach ($nameArray as $index => $key) {
        
            if($key == "_self" && $index != count($nameArray)-1)
                throw new \Exception("Failed to read \"$name\": _self can only be used as tail parameter");

            if(!array_key_exists($key, $normSettings))
                throw new \Exception("Failed to read \"$name\": key not found");

            $normSettings = &$normSettings[$key];
        }

        return $normSettings;
    }
    protected function normalize(string $name, array $settings) {

        $values = [];

        // Generate default structure
        $array = &$values;
        
        $nameArray = explode(".", $name);
        foreach ($nameArray as $index => $key) {
        
            if($key == "_self" && $index != count($nameArray)-1)
                throw new \Exception("Failed to normalize \"$name\": \"_self\" key can only be used as tail parameter");

            if(!array_key_exists($key, $array)) $array[$key] = ["_self" => null];
            $array = &$array[$key];
        }

        // Fill it with settings
        foreach($settings as $setting) {

            $array = &$values;
            foreach (explode(".", $setting->getName()) as $key)
                $array = &$array[$key];
            
            $array["_self"] = $setting;
        }

        return $values;
    }
    
    public function getRaw($name, ?string $locale = null)
    {
        if(!$name) return []; // Empty name is always returning empty data..
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

        try { $this->settings[$name] = $this->settings[$name] ?? $this->settingRepository->findByInsensitiveNameStartingWith($name)->getResult(); } 
        catch(TableNotFoundException $e) { return []; }
        
        $values = $this->normalize($name, $this->settings[$name]);
        $values = $this->read($name, $values); // get formatted values
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
                $settings[] = $this->getRawScalar($name, $locale);

            return $settings;
        }

        return $this->getRaw($name, $locale)["_self"] ?? null;
    }

    public function getScalar($name, ?string $locale = null): ?string
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
            return ($cacheValues !== null ? $cacheValues["_self"] ?? null : null);

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

        $values = $this->getRaw($name, $locale) ?? [];
       	return BaseService::array_map_recursive(function($value) use ($locale) {
               	    return ($value instanceof Setting ? $value->translate($locale)->getValue() : $value);
               	}, $values);
    }

    
    public function set(string $name, string $value, ?string $locale = null)
    {
        if(!$this->settingRepository)
            $this->settingRepository = $this->entityManager->getRepository(Setting::class);

        $this->removeCache($name);

        $setting = $this->getRaw($name)["_self"] ?? null;
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
        if(!$this->cache) return null;
        if(!$this->cacheEnabled) return null;
        
        $item = $this->cache->getItem($name);
        $itemList = $item->get() ?? [];
        if (array_key_exists($locale, $itemList))
            return $itemList[$locale] ?? [];

        return null;
    }

    protected function applyCache(string $name, string $locale, $array)
    {
        if(!$array) return false;
        if(!$this->cache) return false;
        if(!$this->cacheEnabled) return false;
        
        if(($setting = $array) instanceof Setting) 
            return $this->applyCache($name, $locale, $setting->translate($locale)->getValue());

        if(is_array( ($values = $array) )) {

            if(array_key_exists("_self", $values) && ($setting = $values["_self"]) instanceof Setting)
                $values["_self"] = $setting->translate($locale)->getValue();

            $item = $this->cache->getItem($name);
            $localeValues = array_merge($item->get() ?? [], [$locale => $values]);
            foreach($localeValues as $locale => $values) {

                // Broadcast cache storage
                foreach  ($values as $key => $value) {

                    if($key == "_self") continue;
                    $this->applyCache($name.".".$key, $locale, $value);
                }

                // Process current node
                $localeValues[$locale] = BaseService::array_map_recursive(
                    function($value) use ($locale) {
                        return ($value instanceof Setting ? $value->translate($locale)->getValue() : $value);
                    }, $values
                );
            }

            $this->cache->save($item->set($localeValues));
            return true;
        }

        $item = $this->cache->getItem($name);
        $array = array_merge($item->get() ?? [], [$locale => $array]);
        $this->cache->save($item->set($array));

        return true;
    }
    
    public function removeCache(string $name)
    {
        foreach(array_reverse(explode(".", $name)) as $last) {

            $this->cache->delete($name);
            $name = substr($name, 0, strlen($name) - strlen($last) - 1);
        }

        return $this;
    }
}
