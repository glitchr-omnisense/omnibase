<?php

namespace Base\Traits;

use Base\BaseBundle;
use Base\Entity\Sitemap\Setting;

use Doctrine\DBAL\Exception\TableNotFoundException;

trait BaseSettingsTrait
{
    protected $cache = null;
    protected $settingRepository = null;
    protected $settings = [];

    protected function isCacheEnabled()
    {
        if(!BaseBundle::CACHE) return false; /* MUST BE DEFINED */
        if(!$this->cache)    return false;
        if(is_cli())   return false;

        return true;
    }

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

        if(is_array($names = $name)) {
            
            $settings = [];
            foreach($names as $name)
                $settings[] = $this->getRaw($name, $locale);

            return $settings;
        }

        if(array_key_exists($name, $this->settings))
            $this->settings[$name] = !empty($this->settings[$name]) ? $this->settings[$name] : null;

        try { $this->settings[$name] = $this->settings[$name] ?? $this->settingRepository->findByInsensitiveNameStartingWith($name)->getResult(); } 
        catch(TableNotFoundException $e) { return []; }

        $values = $this->normalize($name, $this->settings[$name]);
        $values = $this->read($name, $values); // get formatted values
        $this->applyCache($name, $locale, $values);
        
        return $values;
    }
    
    public function getRawScalar($name, ?string $locale = null)
    {
        if(is_array($names = $name)) {
            
            $settings = [];
            foreach($names as $name)
                $settings[] = $this->getRawScalar($name, $locale);

            return $settings;
        }

        return $this->getRaw($name, $locale)["_self"] ?? null;
    }

    public function getScalar($name, ?string $locale = null): string|array|object|null
    {
        if(is_array($names = $name)) {
            
            $settings = [];
            foreach($names as $name)
                $settings[] = $this->getScalar($name, $locale);

            return $settings;
        }

        return $this->get($name, $locale)["_self"] ?? null;
    }

    public function get($name, ?string $locale = null): array
    {
        if(is_array($names = $name)) {

            $settings = [];
            foreach($names as $name)
                $settings[$name] = $this->get($name, $locale);

            return $settings;
        }

        if(($cacheValues = $this->getCache($name, $locale)))
            return $cacheValues;

        $values = $this->getRaw($name, $locale) ?? [];
        $this->applyCache($name, $locale, $values);
        
        return array_map_recursive(fn($v) => ($v instanceof Setting ? $v->translate($locale)->getValue() : $v), $values);
    }

    public function set(string $name, $value, ?string $locale = null)
    {
        // Delete cache
        $this->removeCache($name);

        // Compute new value or create setting if missing
        $locale = $this->localeProvider->getLocale($locale);
        $setting = $this->getRaw($name, $locale)["_self"];
        if(!$setting instanceof Setting) {
        
            $setting = new Setting($name);
            $this->entityManager->persist($setting);
        }

        $setting->translate($locale)->setValue($value);
        $this->entityManager->flush();

        return $this;
    }

    public function has(string $name, ?string $locale = null)
    {
        return $this->get($name, $locale) !== null;
    }
    
    public function remove(string $name)
    {
        $this->removeCache($name);

        $this->settings[$name] = $this->settings[$name] ?? $this->settingRepository->findOneByInsensitiveName($name);
        if($this->settings[$name] instanceof Setting) {

            $this->entityManager->remove($this->settings[$name]);
            $this->entityManager->flush();    
        }

        return $this;
    }

    protected function getCache(string $name, ?string $locale) : ?array
    {
        if(!$this->isCacheEnabled()) return null;

        $item = $this->cache->getItem($name);
        if(!$item) return null;

        $settings = $item->get();
        if(!is_array($settings)) return null;

        $locale = $this->localeProvider->getLocale($locale);
        return $settings[$locale] ?? null;
    }

    protected function applyCache(string $name, ?string $locale, array $settings)
    {
        if(!$settings) return false;

        // Broadcast cache storage
        foreach  ($settings as $key => $setting) {

            if($key == "_self") continue;
            else if(array_key_exists("_self", $setting))
                $this->applyCache($name.".".$key, $locale, $setting);
        }

        // Process current node
        $settings = array_map_recursive(
            fn($v) => $v instanceof Setting ? $v->translate($locale)->getValue() : $v, 
            $settings);

        if($this->isCacheEnabled()) {

            $item = $this->cache->getItem($name);

            $locale = $this->localeProvider->getLocale($locale);

            $settingsWithLocale = [$locale => $settings];
            $settings = $item->get() ?? [];
            $settings = is_array($settings) ? array_merge($settings, $settingsWithLocale) : $settingsWithLocale;

            $this->cache->save($item->set($settings));
        }

        return true;
    }
    
    public function removeCache(string $name)
    {
        unset($this->settings[$name]);
        foreach(array_reverse(explode(".", $name)) as $last) {

            $this->cache->delete($name);
            $name = substr($name, 0, strlen($name) - strlen($last) - 1);
        }

        return $this;
    }
}
