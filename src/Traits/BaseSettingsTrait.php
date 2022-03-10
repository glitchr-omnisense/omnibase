<?php

namespace Base\Traits;

use Base\BaseBundle;
use Base\Entity\Layout\Setting;

use Doctrine\DBAL\Exception\TableNotFoundException;

trait BaseSettingsTrait
{
    protected $settingRepository = null;

    public function getEnvironment(): ?string { return $this->environment; }
    public function getPaths(null|string|array $path = null) 
    { 
        return array_flatten(
                    array_map_recursive(
                        fn($s) => $s instanceof Setting ? $s->getPath() : null, 
                        array_filter($this->getRaw($path)) ?? []
                    ), ARRAY_FLATTEN_PRESERVE_KEYS
        );
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

        try {

            $fn = $path ? (BaseBundle::CACHE && !is_cli() ? "cacheByInsensitivePathStartingWith" : "findByInsensitivePathStartingWith") :
                          (BaseBundle::CACHE && !is_cli() ? "cacheAll" : "findAll");

            $settings = $this->settingRepository->$fn($path)->getResult();

        } catch(TableNotFoundException $e) { return []; }

        $settings = $this->loadAssociations($settings);

        $values = $this->normalize($path, $settings);
        $values = $this->read($path, $values); // get formatted values

        return $values;
    }

    public function loadAssociations($settings): array
    {
        foreach($settings as $setting) {

            if($setting instanceof Setting) {

                $class = $setting->getClass();
                if($class === null) continue;
                if(!$this->classMetadataManipulator->isEntity($class)) continue;

                $repository = $this->entityManager->getRepository($class);
                foreach($this->getTranslations() as $locale => $translation) {

                    $value = $translation->getValue($locale);

                    $value = $repository->findOneById($value instanceof $class ? $value->getId() : $value);
                    $translation->setValue($value, $locale);
                }
            }
        }

        return $settings;
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

        $values = $this->getRaw($path, $locale) ?? [];

        return array_map_recursive(fn($v) => ($v instanceof Setting ? $v->translate($locale)->getValue() ?? $v->translate($this->localeProvider->getDefaultLocale())->getValue() : $v), $values);
    }

    public function set(string $path, $value, ?string $locale = null)
    {
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
        $settings = $this->settingRepository->findOneByInsensitivePath($path);
        if($settings instanceof Setting) {

            $this->entityManager->remove($settings);
            $this->entityManager->flush();    
        }

        return $this;
    }

    public function lock(string $path  ) { return $this->setLock($path, true); }
    public function unlock(string $path) { return $this->setLock($path, false); }
    public function setLock(string $path, bool $flag = true)
    {
        // Compute new label or create setting if missing
        $setting = $this->getRaw($path)["_self"];
        if(!$setting instanceof Setting) {
        
            $setting = new Setting($path);
            $this->entityManager->persist($setting);
        }

        $setting->setLock($flag);
        $this->entityManager->flush();

        return $this;
    }

    public function secure(string $path  ) { return $this->setSecure($path, true); }
    public function unsecure(string $path) { return $this->setSecure($path, false); }
    public function setSecure(string $path, bool $flag = true)
    {
        // Compute new label or create setting if missing
        $setting = $this->getRaw($path)["_self"];
        if(!$setting instanceof Setting) {
        
            $setting = new Setting($path);
            $this->entityManager->persist($setting);
        }

        $setting->setVault($flag ? $this->getEnvironment() : null);
        $this->entityManager->flush();

        return $this;
    }
}
