<?php

namespace Base\Traits;

use Base\BaseBundle;
use Base\Entity\Layout\Setting;

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

    protected function read(?string $path, array $normSettings)
    {
        if($path === null) return $normSettings;

        $pathArray = explode(".", $path);
        foreach ($pathArray as $index => $key) {

            if($key == "_self" && $index != count($pathArray)-1)
                throw new \Exception("Failed to read \"$path\": _self can only be used as tail parameter");

            if(!array_key_exists($key, $normSettings))
                throw new \Exception("Failed to read \"$path\": key not found");

            $normSettings = &$normSettings[$key];
        }

        return $normSettings;
    }

    public function normalize(?string $path, array $settings) {

        $values = [];

        // Generate default structure
        $array = &$values;

        if($path !== null) {

            $el = explode(".", $path);
            $last = count($el)-1;
            foreach ($el as $index => $key) {
            
                if($key == "_self" && $index != $last)
                    throw new \Exception("Failed to normalize \"$path\": \"_self\" key can only be used as tail parameter");

                if(!array_key_exists($key, $array)) $array[$key] = ["_self" => null];
                $array = &$array[$key];
            }
        }

        // Fill it with settings
        foreach($settings as $setting) {

            $array = &$values;
            foreach (explode(".", $setting->getPath()) as $key)
                $array = &$array[$key];

            $array["_self"] = $setting;
        }

        return $values;
    }

    public function denormalize(array $settings, ?string $path = null) {

        if($path) {

            foreach(explode(".", $path) as $value)
                $settings = $settings[$value];
        }

        $settings = array_transforms(
            fn($k, $v):?array => [str_replace(["_self.", "._self", "_self"], "", $k), $v], 
            array_flatten($settings, ARRAY_FLATTEN_PRESERVE_KEYS)
        );

        foreach($settings as $key => $setting) {

            $matches = [];
            if(preg_match("/(.*)[0-9]+$/", $key, $matches)) {

                $path = $matches[1];
                if(!array_key_exists($path, $settings))
                    $settings[$path] = [];

                $settings[$path][] = $setting;
                unset($settings[$key]);
            }
        }

        return array_filter($settings);
    }
    
    public function getRaw(null|string|array $path = null, ?string $locale = null)
    {
        if(is_array($paths = $path)) {
            
            $settings = [];
            foreach($paths as $path)
                $settings[] = $this->getRaw($path, $locale);

            return $settings;
        }

        if(array_key_exists($path, $this->settings))
            $this->settings[$path] = !empty($this->settings[$path]) ? $this->settings[$path] : null;

        try {

            $this->settings[$path] = $this->settings[$path] ?? [];
            if($this->settings[$path] === []) {

                if(!$path) $this->settings[$path] = $this->settingRepository->findAll()->getResult();
                else $this->settings[$path] = $this->settingRepository->findByInsensitivePathStartingWith($path)->getResult();

            }

        } catch(TableNotFoundException $e) { return []; }

        $values = $this->normalize($path, $this->settings[$path]);
        $values = $this->read($path, $values); // get formatted values
        $this->applyCache($path, $locale, $values);

        return $values;
    }
    
    public function getRawScalar(null|string|array $path = null, ?string $locale = null)
    {
        if(is_array($paths = $path)) {
            
            $settings = [];
            foreach($paths as $path)
                $settings[] = $this->getRawScalar($path, $locale);

            return $settings;
        }

        return $this->getRaw($path, $locale)["_self"] ?? null;
    }

    public function getScalar(null|string|array $path, ?string $locale = null): string|array|object|null
    {
        if(is_array($paths = $path)) {
            
            $settings = [];
            foreach($paths as $path)
                $settings[] = $this->getScalar($path, $locale);

            return $settings;
        }

        return $this->get($path, $locale)["_self"] ?? null;
    }

    public function get(null|string|array $path = null, ?string $locale = null): array
    {
        if(is_array($paths = $path)) {

            $settings = [];
            foreach($paths as $path)
                $settings[$path] = $this->get($path, $locale);

            return $settings;
        }

        if(($cacheValues = $this->getCache($path, $locale)))
            return $cacheValues;

        $values = $this->getRaw($path, $locale) ?? [];
        $this->applyCache($path, $locale, $values);
        
        return array_map_recursive(fn($v) => ($v instanceof Setting ? $v->translate($locale)->getValue() : $v), $values);
    }

    public function set(string $path, $value, ?string $locale = null)
    {
        // Delete cache
        $this->removeCache($path);

        // Compute new value or create setting if missing
        $locale = $this->localeProvider->getLocale($locale);
        $setting = $this->getRaw($path, $locale)["_self"];
        if(!$setting instanceof Setting) {
        
            $setting = new Setting($path);
            $this->entityManager->persist($setting);
        }

        $setting->translate($locale)->setValue($value);
        $this->entityManager->flush();

        return $this;
    }

    public function setLabel(string $path, ?string $label = null, ?string $locale = null)
    {
        // Delete cache
        $this->removeCache($path);

        // Compute new label or create setting if missing
        $locale = $this->localeProvider->getLocale($locale);
        $setting = $this->getRaw($path, $locale)["_self"];
        if(!$setting instanceof Setting) {
        
            $setting = new Setting($path);
            $this->entityManager->persist($setting);
        }

        $setting->translate($locale)->setLabel($label);
        $this->entityManager->flush();

        return $this;
    }

    public function setHelp(string $path, ?string $help = null, ?string $locale = null)
    {
        // Delete cache
        $this->removeCache($path);

        // Compute new help or create setting if missing
        $locale = $this->localeProvider->getLocale($locale);
        $setting = $this->getRaw($path, $locale)["_self"];
        if(!$setting instanceof Setting) {
        
            $setting = new Setting($path);
            $this->entityManager->persist($setting);
        }

        $setting->translate($locale)->setHelp($help);
        $this->entityManager->flush();

        return $this;
    }

    public function has(string $path, ?string $locale = null)
    {
        return $this->get($path, $locale) !== null;
    }
    
    public function remove(string $path)
    {
        $this->removeCache($path);

        $this->settings[$path] = $this->settings[$path] ?? $this->settingRepository->findOneByInsensitivePath($path);
        if($this->settings[$path] instanceof Setting) {

            $this->entityManager->remove($this->settings[$path]);
            $this->entityManager->flush();    
        }

        return $this;
    }

    protected function getCache(?string $path, ?string $locale) : ?array
    {
        if(!$this->isCacheEnabled()) return null;

        $item = $this->cache->getItem($path);
        if(!$item) return null;

        $settings = $item->get();
        if(!is_array($settings)) return null;

        $locale = $this->localeProvider->getLocale($locale);
        return $settings[$locale] ?? null;
    }

    protected function applyCache(?string $path, ?string $locale, array $settings)
    {
        if(!$settings) return false;

        // Broadcast cache storage
        foreach  ($settings as $key => $setting) {

            if($key == "_self") continue;
            else if(array_key_exists("_self", $setting))
                $this->applyCache($path.".".$key, $locale, $setting);
        }

        // Process current node
        $settings = array_map_recursive(
            fn($v) => $v instanceof Setting ? $v->translate($locale)->getValue() : $v, 
            $settings);

        if($this->isCacheEnabled()) {

            $item = $this->cache->getItem($path);

            $locale = $this->localeProvider->getLocale($locale);

            $settingsWithLocale = [$locale => $settings];
            $settings = $item->get() ?? [];
            $settings = is_array($settings) ? array_merge($settings, $settingsWithLocale) : $settingsWithLocale;

            $settings = array_map_recursive(fn($i) => is_serializable($i) ? $i : null, $settings);

            $this->cache->save($item->set($settings));
        }

        return true;
    }
    
    public function removeCache(string $path)
    {
        unset($this->settings[$path]);
        foreach(array_reverse(explode(".", $path)) as $last) {

            $this->cache->delete($path);
            $path = substr($path, 0, strlen($path) - strlen($last) - 1);
        }

        return $this;
    }
}
