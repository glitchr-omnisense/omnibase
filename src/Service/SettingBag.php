<?php

namespace Base\Service;

use Base\BaseBundle;
use Base\Entity\Layout\Setting;
use Base\Repository\Layout\SettingRepository;
use Symfony\Component\Asset\Packages;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Query;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Contracts\Cache\CacheInterface;

class SettingBag implements SettingBagInterface, WarmableInterface
{
    /**
     * @var Packages
     */
    protected $packages;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var CacheInterface
     */
    protected $cacheSettingBag;

    /**
     * @var LocalProvider
     */
    protected $localeProvider;

    /**
     * @var SettingRepository
     */
    protected $settingRepository = null;

    protected ?string $environment;
    
    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var string
     */
    protected string $cacheName;

    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;

    public function warmUp(string $cacheDir): array
    {
        $this->all();
        $this->allRaw();

        return [ get_class($this) ];
    }

    public function __construct(ParameterBagInterface $parameterBag, EntityManagerInterface $entityManager, LocaleProviderInterface $localeProvider, Packages $packages, CacheInterface $cache, string $environment)
    {
        $this->parameterBag      = $parameterBag;
        $this->entityManager     = $entityManager;
        $this->settingRepository = $entityManager->getRepository(Setting::class);

        $this->cache           = $cache;
        $this->cacheName       = "setting_bag." . hash('md5', self::class);
        $this->cacheSettingBag = $cache->getItem($this->cacheName);

        $this->packages       = $packages;
        $this->localeProvider = $localeProvider;
        $this->environment    = $environment;
    }

    public function all   (?string $locale = null) : array   { return $this->get(null, $locale); }
    public function allRaw($useCache = BaseBundle::USE_CACHE, $onlyLinkedBag = false) : array  { return $this->getRaw(null, $useCache, $onlyLinkedBag); }
    public function __call($name, $_) { return $this->get("base.settings.".$name); }

    public function getEnvironment(): ?string { return $this->environment; }
    public function getPaths(null|string|array $path = null)
    {
        return array_map(fn($s) => $s instanceof Setting ? $s->getPath() : null, array_filter_recursive($this->getRaw($path)) ?? []);
    }

    protected function read(?string $path, array $bag)
    {
        if($path === null) return $bag;

        $pathArray = explode(".", $path);
        foreach ($pathArray as $index => $key) {

            if($key == "_self" && $index != count($pathArray)-1)
                throw new \Exception("Failed to read \"$path\": _self can only be used as tail parameter");

            if(!array_key_exists($key, $bag))
                throw new \Exception("Failed to read \"$path\": key not found");

            $bag = &$bag[$key];
        }

        return $bag;
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
            array_flatten(".", $settings, -1, ARRAY_FLATTEN_PRESERVE_KEYS)
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

    public function getRaw(null|string|array $path = null, bool $useCache = BaseBundle::USE_CACHE, bool $onlyLinkedBag = false)
    {
        $useSettingBag = $this->parameterBag->get("base.parameter_bag.use_setting_bag") ?? false;
        if(!$useSettingBag)
            return [];

        $this->clear($path);
        
        if(is_array($paths = $path)) {

            $settings = [];
            foreach($paths as $path)
                $settings[] = $this->getRaw($path, $useCache);

            return $settings;
        }

        if(!$this->settingRepository) throw new InvalidArgumentException("Setting repository not found. No doctrine connection established ?");

        try {

            $fn  = $useCache ? "cache" : "find";
            $fn .= $onlyLinkedBag ?
                    ($path ? "ByInsensitivePathStartingWithAndBagNotEmpty" : "ByBagNotEmpty") :
                    ($path ? "ByInsensitivePathStartingWith" : "All");

            $args = $path ? [$path] : [];

            $settings = $this->settingRepository->$fn(...$args);
            if ($settings instanceof Query)
                $settings = $settings->getResult();

        } catch(TableNotFoundException  $e) { throw $e; }
          catch(EntityNotFoundException $e) {

            return $useCache ? $this->getRaw($path, false) : [];
        
        } // Cache fallback

        $values = $this->normalize($path, $settings);
        $values = $this->read($path, $values); // get formatted values

        return $values;
    }

    public function generateRaw(string $path, ?string $locale = null, bool $useCache = false): Setting
    {
        $locale = $this->localeProvider->getLocale($locale);
        $setting = $this->getRawScalar($path, $useCache);

        if(!$setting instanceof Setting) {

            $setting = new Setting($path, null, $locale);
            $this->entityManager->persist($setting);
        }

        return $setting;
    }

    public function getRawScalar(null|string|array $path = null, bool $useCache = BaseBundle::USE_CACHE)
    {
        if(is_array($paths = $path)) {

            $settings = [];
            foreach($paths as $path)
                $settings[] = $this->getRawScalar($path, $useCache);

            return $settings;
        }

        return $this->getRaw($path, $useCache)["_self"] ?? null;
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

    protected array $settingBag = [];
    public function get(null|string|array $path = null, ?string $locale = null, ?bool $useCache = BaseBundle::USE_CACHE): array
    {
        if(is_array($paths = $path)) {

            $settings = [];
            foreach($paths as $path)
                $settings[$path] = $this->get($path, $locale, $useCache);

            return $settings;
        }

        $this->settingBag ??= $useCache && $this->cacheSettingBag !== null ? $this->cacheSettingBag->get() ?? [] : [];
        if(array_key_exists($path.":".($locale ?? LocaleProvider::UNIVERSAL), $this->settingBag))
            return $this->settingBag[$path.":".($locale ?? LocaleProvider::UNIVERSAL)];

        try { $values = $this->getRaw($path, $useCache) ?? []; }
        catch (Exception $e) { throw $e; return []; }

        $this->settingBag[$path.":".($locale ?? LocaleProvider::UNIVERSAL)] ??= array_map_recursive(function($v) use ($locale) {

            if(!$v instanceof Setting) return $v;
            return $v->translate($locale)?->getValue() ?? $v->translate($this->localeProvider->getDefaultLocale())?->getValue();

        }, $values);

        if($useCache) $this->cache->save($this->cacheSettingBag->set($this->settingBag));

        return $this->settingBag[$path.":".($locale ?? LocaleProvider::UNIVERSAL)];
    }

    public function clearAll() { return $this->clear(null); }
    public function clear(null|string|array $path, ?string $locale = null, $useCache = BaseBundle::USE_CACHE)
    {
        if(is_array($paths = $path)) {

            foreach($paths as $path)
                $this->clear($path, $locale, $useCache);

            return;
        }

        if(!$path) $this->settingBag = [];
        else {

            $pathByArray = explode(".", $path);
            while( !empty($pathByArray) ) {

                $currentPath = implode(".", $pathByArray);
                $this->settingBag = array_key_removes_startsWith($this->settingBag, true, $currentPath.":");

                array_pop($pathByArray);
            }
        }

        if($useCache) $this->cache->save($this->cacheSettingBag->set($this->settingBag));
    }

    public function set(string $path, $value, ?string $locale = null, $useCache = BaseBundle::USE_CACHE)
    {
        $setting = $this->generateRaw($path, $locale);
        if($setting->isLocked())
            throw new \Exception("Setting \"$path\" is locked and cannot be modified.");

        $setting->translate($locale)->setValue($value);
        $this->clear($path, $locale);

        if ($this->entityManager->getCache())
            $this->entityManager->getCache()->evictEntity(get_class($setting), $setting->getId());

        $this->entityManager->flush();
        return $this;
    }

    public function setLabel(string $path, ?string $label = null, ?string $locale = null)
    {
        $setting = $this->generateRaw($path, $locale);
        $setting->translate($locale)->setLabel($label);
        if ($this->entityManager->getCache())
            $this->entityManager->getCache()->evictEntity(get_class($setting), $setting->getId());

        $this->entityManager->flush();
        return $this;
    }

    public function setHelp(string $path, ?string $help = null, ?string $locale = null)
    {
        $setting = $this->generateRaw($path, $locale);
        $setting->translate($locale)->setHelp($help);
        if ($this->entityManager->getCache())
            $this->entityManager->getCache()->evictEntity(get_class($setting), $setting->getId());

        $this->entityManager->flush();
        return $this;
    }

    public function setBag(string $path, ?string $parameterName = null)
    {
        $setting = $this->generateRaw($path);
        $setting->setBag($parameterName);
        if ($this->entityManager->getCache())
            $this->entityManager->getCache()->evictEntity(get_class($setting), $setting->getId());

        $this->entityManager->flush();
        return $this;
    }

    public function has(string $path, ?string $locale = null)
    {
        return $this->get($path, $locale) !== null;
    }

    public function remove(string $path)
    {
        $setting = $this->settingRepository->findOneByInsensitivePath($path);
        if($setting instanceof Setting) {

            unset($this->settingBag[$path]);

            $this->entityManager->remove($setting);
            $this->entityManager->flush();
        }

        return $this;
    }

    public function lock(string $path  ) { return $this->setLock($path, true); }
    public function unlock(string $path) { return $this->setLock($path, false); }
    public function setLock(string $path, bool $flag = true)
    {
        $setting = $this->generateRaw($path);
        $setting->setLocked($flag);

        $this->entityManager->flush();
        return $this;
    }

    public function secure(string $path  ) { return $this->setSecure($path, true); }
    public function unsecure(string $path) { return $this->setSecure($path, false); }
    public function setSecure(string $path, bool $flag = true)
    {
        $setting = $this->generateRaw($path);
        $setting->setVault($flag ? $this->getEnvironment() : null);
        if ($this->entityManager->getCache())
            $this->entityManager->getCache()->evictEntity(get_class($setting), $setting->getId());

        $this->entityManager->flush();
        return $this;
    }
}
