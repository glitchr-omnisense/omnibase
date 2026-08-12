<?php

namespace Base\Service;

use Base\BaseBundle;
use Base\Entity\Layout\Setting;
use Base\Entity\Layout\SettingIntl;
use Base\Repository\Layout\SettingRepository;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Asset\Packages;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Query;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class SettingBag implements SettingBagInterface, CacheWarmerInterface
{
    /**
     * @var Packages
     */
    protected Packages $packages;

    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;

    /**
     * @var CacheItemInterface|null
     */
    protected ?CacheItemInterface $cacheSettingBag = null;

    /**
     * In-memory copy of the compiled snapshot for this request/process, once
     * loaded from cache (or compiled fresh). Shape: ['tree' => <normalize()
     * shape, with each Setting entity leaf replaced by a SettingSnapshotValue>,
     * 'bags' => [bagParameterName => settingPath]].
     *
     * @var array{tree: array, bags: array<string, string>}|null
     */
    private ?array $snapshot = null;

    /**
     * @var LocalizerInterface
     */
    protected LocalizerInterface $localizer;

    /**
     * @var ?SettingRepository
     */
    protected ?SettingRepository $settingRepository = null;

    protected ?string $environment;

    /**
     * @var CacheInterface
     */
    protected CacheInterface $cache;

    /**
     * @var string
     */
    protected string $cacheName;

    /**
     * @var ParameterBagInterface
     */
    protected ParameterBagInterface $parameterBag;

    /**
     * Compile the snapshot fresh (bypassing any cached copy) and persist it,
     * so the very first request after a deploy/cache:clear hits a warm cache
     * instead of compiling on-demand.
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        // Never let an unreachable database turn cache warmup into a fatal:
        // container entrypoints run cache:clear at boot, and a DB that is
        // still starting (or temporarily down) would otherwise crash-loop the
        // whole container (observed during the 2026-07-06 promotion). The
        // snapshot compiles lazily on first read anyway.
        try {
            $this->invalidate();
            $this->loadSnapshot(true);
            $this->allRaw();
        } catch (\Doctrine\DBAL\Exception | \PDOException $e) {
            // degrade: warm nothing, compile on first request instead
        }

        return [get_class($this)];
    }

    /**
     * The snapshot compiles on-demand on a cache miss regardless (see
     * loadSnapshot()), so this warmer is safe to skip under --no-optional-warmers.
     */
    public function isOptional(): bool
    {
        return true;
    }

    public function __construct(ParameterBagInterface $parameterBag, EntityManagerInterface $entityManager, SettingRepository $settingRepository, LocalizerInterface $localizer, Packages $packages, CacheInterface $cache, string $environment)
    {
        $this->parameterBag = $parameterBag;
        $this->entityManager = $entityManager;
        $this->settingRepository = $settingRepository;

        $this->cache = $cache;
        $this->cacheName = "setting_bag." . hash('md5', self::class);
        // Defer cache item fetch to first use (lazy loading)

        $this->packages = $packages;
        $this->localizer = $localizer;
        $this->environment = $environment;
    }

    /**
     * Lazy-load cache item on first access
     */
    private function getCacheItem(): CacheItemInterface
    {
        if ($this->cacheSettingBag === null) {
            $this->cacheSettingBag = $this->cache->getItem($this->cacheName);
        }
        return $this->cacheSettingBag;
    }

    /**
     * Drop the in-memory and persisted snapshot. The next read recompiles it
     * from the database in one pass. Called by SettingBag::set() and by
     * SettingSubscriber on any Setting/SettingIntl write (including ones that
     * bypass SettingBag entirely, e.g. the admin CRUD's plain flush()).
     */
    public function invalidate(): void
    {
        $this->snapshot = null;
        $this->cache->delete($this->cacheName);
        $this->cacheSettingBag = null;
    }

    /**
     * Load the compiled snapshot, from the in-memory copy, then the PSR-6
     * cache, then a fresh compile — written back synchronously (no deferred
     * write, no partial per-path cache keys).
     */
    private function loadSnapshot(bool $useCache = true): array
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }

        if ($useCache) {
            $item = $this->getCacheItem();
            if ($item->isHit()) {
                return $this->snapshot = $item->get();
            }
        }

        $snapshot = $this->compileSnapshot();

        if ($useCache) {
            $item = $this->getCacheItem();
            $item->set($snapshot);
            $this->cache->save($item);
        }

        return $this->snapshot = $snapshot;
    }

    /**
     * One query (findAll(), translations already association-mapped) compiled
     * into the full nested path tree (see normalize()), with every Setting
     * entity leaf replaced by an opaque per-locale SettingSnapshotValue, plus
     * a flat map of bag-linked settings for HotParameterBagSubscriber. Small
     * dataset (tens of rows) — correctness of a single pass wins over
     * micro-optimizing this compile step, which only runs on warmup/miss/
     * invalidation, never on the request hot path.
     */
    private function compileSnapshot(): array
    {
        $useSettingBag = $this->parameterBag->get("base.parameter_bag.use_setting_bag") ?? false;
        if (!$useSettingBag) {
            return ["tree" => [], "bags" => []];
        }

        if (!$this->settingRepository) {
            throw new InvalidArgumentException("Setting repository not found. No doctrine connection established ?");
        }

        $settings = $this->settingRepository->findAll();

        $bags = [];
        foreach ($settings as $setting) {
            if ($setting->getBag() !== null) {
                $bags[$setting->getBag()] = $setting->getPath();
            }
        }

        $tree = $this->normalize(null, $settings);
        $tree = array_map_recursive(function ($setting) {
            if (!$setting instanceof Setting) {
                return $setting;
            }

            // RAW values only (getValueRaw, not getValue): the Uploader
            // public-URL resolution for file-backed settings (e.g. the site
            // logo) depends on the ACTIVE media storage, so baking the derived
            // URL into the snapshot would couple the settings cache to
            // whatever storage was configured at compile time. Resolution
            // happens at read time in get() instead.
            $values = [];
            foreach ($setting->getTranslations() as $locale => $translation) {
                $values[$locale] = $translation->getValueRaw();
            }

            return new SettingSnapshotValue($values);
        }, $tree);

        return ["tree" => $tree, "bags" => $bags];
    }

    /**
     * Apply the same value resolution SettingIntl::getValue() performs on a
     * live entity (Uploader public-URL resolution for file-backed values,
     * passthrough for everything else) to a raw snapshot value — through the
     * genuine entity accessor on a throwaway instance, so the two paths can
     * never drift apart.
     */
    private function resolveRawValue(mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        return (new SettingIntl())->setValue($raw)->getValue();
    }

    /**
     * Flat [bagParameterName => resolvedDefaultLocaleValue] map, read straight
     * off the compiled snapshot — no query, no entity hydration. Replaces
     * HotParameterBagSubscriber's former allRaw(true, true) + recursive walk,
     * which re-ran on every request/console command for what is typically a
     * handful of bag-linked settings.
     */
    public function getBagParameters(): array
    {
        $snapshot = $this->loadSnapshot();
        $defaultLocale = $this->localizer->getDefaultLocale();

        $parameters = [];
        foreach ($snapshot["bags"] as $bagParameter => $path) {
            $parameters[$bagParameter] = $this->getScalar($path, $defaultLocale);
        }

        return $parameters;
    }

    public function all(?string $locale = null): array
    {
        return $this->get(null, $locale);
    }

    /**
     * @param $useCache
     * @param $onlyLinkedBag
     * @return array
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function allRaw($useCache = true, $onlyLinkedBag = false): array
    {
        return $this->getRaw(null, $useCache, $onlyLinkedBag);
    }

    /**
     * @param $name
     * @param $_
     * @return array
     * @throws Exception
     */
    public function __call($name, $_)
    {
        return $this->get("base.settings." . $name);
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    /**
     * @param string|array|null $path
     * @return array|null[]|string[]
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getPaths(null|string|array $path = null)
    {
        return array_map(fn($s) => $s instanceof Setting ? $s->getPath() : null, array_filter_recursive($this->getRaw($path)) ?? []);
    }

    /**
     * @param string|null $path
     * @param array $bag
     * @return array|mixed
     * @throws Exception
     */
    protected function read(?string $path, array $bag)
    {
        if ($path === null) {
            return $bag;
        }

        $pathArray = explode(".", $path);
        foreach ($pathArray as $index => $key) {
            if ($key == "_self" && $index != count($pathArray) - 1) {
                throw new Exception("Failed to read \"$path\": _self can only be used as tail parameter");
            }

            if (!array_key_exists($key, $bag)) {
                throw new Exception("Failed to read \"$path\": key not found");
            }

            $bag = &$bag[$key];
        }

        return $bag;
    }

    /**
     * @param string|null $path
     * @param array $settings
     * @return array|mixed|null[]
     * @throws Exception
     */
    public function normalize(?string $path, array $settings)
    {
        $values = [];

        // Generate default structure
        $array = &$values;

        if ($path !== null) {
            $el = explode(".", $path);
            $last = count($el) - 1;
            foreach ($el as $index => $key) {
                if ($key == "_self" && $index != $last) {
                    throw new Exception("Failed to normalize \"$path\": \"_self\" key can only be used as tail parameter");
                }

                if (!array_key_exists($key, $array)) {
                    $array[$key] = ["_self" => null];
                }
                $array = &$array[$key];
            }
        }

        // Fill it with settings
        foreach ($settings as $setting) {
            $array = &$values;
            foreach (explode(".", $setting->getPath()) as $key) {
                $array = &$array[$key];
            }

            $array["_self"] = $setting;
        }

        return $values;
    }

    /**
     * @param array $settings
     * @param string|null $path
     * @return array
     * @throws Exception
     */
    public function denormalize(array $settings, ?string $path = null)
    {
        if ($path) {
            foreach (explode(".", $path) as $value) {
                $settings = $settings[$value];
            }
        }

        $settings = array_transforms(
            fn($k, $v): ?array => [str_replace(["_self.", "._self", "_self"], "", $k), $v],
            array_flatten(".", $settings, -1, ARRAY_FLATTEN_PRESERVE_KEYS)
        );

        foreach ($settings as $key => $setting) {
            $matches = [];
            if (preg_match("/(.*)[0-9]+$/", $key, $matches)) {
                $path = $matches[1];
                if (!array_key_exists($path, $settings)) {
                    $settings[$path] = [];
                }

                $settings[$path][] = $setting;
                unset($settings[$key]);
            }
        }

        return array_filter($settings);
    }

    /**
     * @param string|array|null $path
     * @param bool $useCache
     * @param bool $onlyLinkedBag
     * @return array|mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function getRaw(null|string|array $path = null, bool $useCache = true, bool $onlyLinkedBag = false)
    {
        $useSettingBag = $this->parameterBag->get("base.parameter_bag.use_setting_bag") ?? false;
        if (!$useSettingBag) {
            return [];
        }

        if (!$useCache) {
            $this->clear($path);
        }

        if (is_array($paths = $path)) {
            $settings = [];
            foreach ($paths as $path) {
                $settings[] = $this->getRaw($path, $useCache);
            }

            return $settings;
        }

        if (!$this->settingRepository) {
            throw new InvalidArgumentException("Setting repository not found. No doctrine connection established ?");
        }

        try {
            $fn = $useCache ? "cache" : "find";
            $fn .= $onlyLinkedBag ?
                ($path ? "ByInsensitivePathStartingWithAndBagNotEmpty" : "ByBagNotEmpty") :
                ($path ? "ByInsensitivePathStartingWith" : "All");

            $args = $path ? [$path] : [];

            $settings = $this->settingRepository->$fn(...$args);
            if ($settings instanceof Query) {
                $settings = $settings->getResult();
            }
        } catch (EntityNotFoundException $e) {
            return $useCache ? $this->getRaw($path, false) : [];
        } // Cache fallback

        $values = $this->normalize($path, $settings);
        // get formatted values

        return $this->read($path, $values);
    }

    public function generateRaw(string $path, ?string $locale = null, bool $useCache = false): Setting
    {
        $locale = $this->localizer->getLocale($locale);
        $setting = $this->getRawScalar($path, $useCache);

        if (!$setting instanceof Setting) {
            $setting = new Setting($path, null, $locale);
            $this->entityManager->persist($setting);
        }

        return $setting;
    }

    /**
     * @param string|array|null $path
     * @param bool $useCache
     * @return array|mixed|null
     */
    public function getRawScalar(null|string|array $path = null, bool $useCache = true)
    {
        if (is_array($paths = $path)) {
            $settings = [];
            foreach ($paths as $path) {
                $settings[] = $this->getRawScalar($path, $useCache);
            }

            return $settings;
        }

        return $this->getRaw($path, $useCache)["_self"] ?? null;
    }

    public function getScalar(null|string|array $path, ?string $locale = null): mixed
    {
        if (is_array($paths = $path)) {
            $settings = [];
            foreach ($paths as $path) {
                $settings[] = $this->getScalar($path, $locale);
            }

            return $settings;
        }

        return $this->get($path, $locale)["_self"] ?? null;
    }

    public function get(null|string|array $path = null, ?string $locale = null, ?bool $useCache = true): array
    {
        if (is_array($paths = $path)) {
            $settings = [];
            foreach ($paths as $path) {
                $settings[$path] = $this->get($path, $locale, $useCache);
            }

            return $settings;
        }

        if (!$useCache) {
            $this->invalidate();
        }

        $snapshot = $this->loadSnapshot($useCache);

        try {
            $values = $this->read($path, $snapshot["tree"]) ?? [];
        } catch (Exception $e) {
            // The compiled tree only has branches for settings that actually
            // exist in the DB. The old per-path getRaw($path) flow queried
            // "WHERE path STARTS WITH $path" and fed the (possibly empty)
            // result into normalize($path, ...), which synthesizes an empty
            // ["_self" => null] skeleton for the requested path regardless of
            // whether any matching row exists — so a well-formed but never-
            // configured path degraded to null rather than erroring. Preserve
            // that here; still surface genuine path-syntax errors (e.g. "_self"
            // used mid-path).
            if (str_contains($e->getMessage(), "key not found")) {
                $values = ["_self" => null];
            } else {
                throw $e;
            }
        }

        $normLocale = $locale !== null ? $this->localizer->getLocale($locale) : null;
        $defaultLocale = $this->localizer->getDefaultLocale();

        return array_map_recursive(function ($entry) use ($normLocale, $defaultLocale) {
            if (!$entry instanceof SettingSnapshotValue) {
                return $entry;
            }

            $raw = $entry->values[$normLocale]
                ?? $entry->values[$defaultLocale]
                ?? (empty($entry->values) ? null : first($entry->values));

            return $this->resolveRawValue($raw);
        }, $values);
    }

    public function clearAll()
    {
        $this->clear(null);
    }

    /**
     * Invalidates the whole compiled snapshot. $path/$locale are accepted for
     * backward compatibility with call sites that used to target a specific
     * cache key, but a single small snapshot is cheap enough to recompile
     * wholesale — there is no meaningful partial-invalidate left to do.
     *
     * @param string|array|null $path
     * @param string|null $locale
     * @return void
     */
    public function clear(null|string|array $path, ?string $locale = null, $useCache = true)
    {
        if (!$useCache) {
            return;
        }

        $this->invalidate();
    }

    /**
     * @param string $path
     * @param $value
     * @param string|null $locale
     * @param $useCache
     * @return $this
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function set(string $path, $value, ?string $locale = null, $useCache = true)
    {
        $setting = $this->generateRaw($path, $locale);
        if ($setting->isLocked()) {
            throw new Exception("Setting \"$path\" is locked and cannot be modified.");
        }

        $translation = $setting->translate($locale);
        $translation->setValue($value);

        // Writing null has to DELETE the translation, not just null the
        // object: SettingIntl::isEmpty() is `value === null`, and the
        // translatable behaviour skips persisting an empty translation - so
        // the row already in the database survived untouched and set($path,
        // null) silently did nothing at all. Found while clearing settings
        // corrupted by a bad save: the old value kept coming back.
        if (null === $value && null !== $translation->getId()) {
            $setting->removeTranslation($translation);
            $this->entityManager->remove($translation);
        }

        $this->clear($path, $locale);

        if ($this->entityManager->getCache()) {
            $this->entityManager->getCache()->evictEntity(get_class($setting), $setting->getId());
            foreach($setting->getTranslations() as $translation)
                $this->entityManager->getCache()->evictEntity(get_class($setting)::getTranslationEntityClass(), $translation->getId());
        }

        $this->entityManager->flush();
        return $this;
    }

    /**
     * @param string $path
     * @param string|null $label
     * @param string|null $locale
     * @return $this
     * @throws Exception
     */
    public function setLabel(string $path, ?string $label = null, ?string $locale = null)
    {
        $setting = $this->generateRaw($path, $locale);
        $setting->translate($locale)->setLabel($label);
        if ($this->entityManager->getCache()) {
            $this->entityManager->getCache()->evictEntity(get_class($setting), $setting->getId());
        }

        $this->entityManager->flush();
        return $this;
    }

    /**
     * @param string $path
     * @param string|null $help
     * @param string|null $locale
     * @return $this
     * @throws Exception
     */
    public function setHelp(string $path, ?string $help = null, ?string $locale = null)
    {
        $setting = $this->generateRaw($path, $locale);
        $setting->translate($locale)->setHelp($help);
        if ($this->entityManager->getCache()) {
            $this->entityManager->getCache()->evictEntity(get_class($setting), $setting->getId());
        }

        $this->entityManager->flush();
        return $this;
    }

    /**
     * @param string $path
     * @param string|null $parameterName
     * @return $this
     */
    public function setBag(string $path, ?string $parameterName = null)
    {
        $setting = $this->generateRaw($path);
        $setting->setBag($parameterName);
        if ($this->entityManager->getCache()) {
            $this->entityManager->getCache()->evictEntity(get_class($setting), $setting->getId());
        }

        $this->entityManager->flush();
        return $this;
    }

    /**
     * @param string $path
     * @param string|null $locale
     * @return bool
     * @throws Exception
     */
    public function has(string $path, ?string $locale = null)
    {
        return $this->get($path, $locale) !== null;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function remove(string $path)
    {
        $setting = $this->settingRepository->findOneByInsensitivePath($path);
        if ($setting instanceof Setting) {
            unset($this->settingBag[$path]);

            $this->entityManager->remove($setting);
            $this->entityManager->flush();
        }

        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function lock(string $path)
    {
        return $this->setLock($path);
    }

    /**
     * @param string $path
     * @return $this
     */
    public function unlock(string $path)
    {
        return $this->setLock($path, false);
    }

    /**
     * @param string $path
     * @param bool $flag
     * @return $this
     */
    public function setLock(string $path, bool $flag = true)
    {
        $setting = $this->generateRaw($path);
        $setting->setLocked($flag);

        $this->entityManager->flush();
        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function secure(string $path)
    {
        return $this->setSecure($path);
    }

    /**
     * @param string $path
     * @return $this
     */
    public function unsecure(string $path)
    {
        return $this->setSecure($path, false);
    }

    /**
     * @param string $path
     * @param bool $flag
     * @return $this
     */
    public function setSecure(string $path, bool $flag = true)
    {
        $setting = $this->generateRaw($path);
        $setting->setVault($flag ? $this->getEnvironment() : null);
        if ($this->entityManager->getCache()) {
            $this->entityManager->getCache()->evictEntity(get_class($setting), $setting->getId());
        }

        $this->entityManager->flush();
        return $this;
    }
}
