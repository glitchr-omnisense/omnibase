<?php

namespace Base\Traits;

use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Entity\EntityHydratorInterface;
use Base\Service\BaseService;
use Base\Service\Obfuscator;
use Base\Service\TradingInterface;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\SettingBag;
use Base\Service\IconProvider;
use Base\Service\MediaServiceInterface;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBagInterface;
use Twig\Environment;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use Psr\Container\ContainerInterface as RuntimeLocatorInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;

trait BaseCommonTrait
{
    /**
     * Lazy runtime locator (a compile-time ServiceLocator holding service
     * CLOSURES, seeded in BaseBundle::boot() — zero services constructed at
     * seed time). Static accessors fall back to it when their static property
     * has not been populated yet, so BaseTrait keeps working everywhere
     * (entities, attributes, bare-CLI kernel boots) WITHOUT requiring
     * BaseService's full dependency graph to be eagerly built first.
     *
     * @var RuntimeLocatorInterface|null
     */
    protected static ?RuntimeLocatorInterface $runtime = null;

    /**
     * Installing a DIFFERENT locator means a different kernel, and everything
     * memoised from the previous one (by runtimeGet() or by an eagerly built
     * BaseService's set*() calls) belongs to a container that is gone. Those
     * statics are cleared, so every accessor re-resolves lazily from the new
     * kernel - the process then behaves exactly like a fresh first boot.
     *
     * Without this, a second kernel in the same process silently ran on the
     * FIRST kernel's services. It never shows in production (an FPM request
     * or a messenger worker boots once), but any process that boots twice
     * does - a PHPUnit run of several KernelTestCase tests that persist
     * entities failed three different ways at once: "The kernel service is
     * synthetic", "Undefined array key preQuery" (a dead event manager), and
     * "Column uuid cannot be null" (GenerateUuid wired to the old one).
     *
     * The first installation (from null) resets nothing: there is nothing
     * stale yet, and values pre-filled before it must survive. Re-installing
     * the SAME locator resets nothing either.
     *
     * projectDir/environment are reset too; BaseBundle::boot() re-seeds both
     * right after calling this.
     */
    public static function setRuntime(?RuntimeLocatorInterface $runtime): void
    {
        if (self::$runtime !== null && $runtime !== self::$runtime) {
            self::forgetRuntimeServices();
        }

        self::$runtime = $runtime;
    }

    /**
     * Every static this trait declares, back to null - enumerated by
     * reflection rather than by hand, so an accessor added later is covered
     * without anyone having to remember this method. Deliberately no "skip
     * the non-nullable ones" guard: a future non-nullable static would make
     * this throw in the multi-kernel tests (loud) instead of being skipped
     * and silently staying stale.
     */
    private static function forgetRuntimeServices(): void
    {
        foreach ((new \ReflectionClass(BaseCommonTrait::class))->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
            $name = $property->getName();
            if ($name !== 'runtime') {
                self::$$name = null;
            }
        }
    }

    /**
     * Resolve a static property, lazily pulling the backing service from the
     * runtime locator on first access. Memoizes into the static property, so
     * each service is resolved at most once per RUNTIME (per kernel; see
     * setRuntime(), which clears these when a new kernel boots) — and an eagerly
     * constructed BaseService (which still calls the set*() methods) simply
     * pre-fills the same properties.
     *
     * @return mixed|null
     */
    public static function runtimeGet(string $property, string $serviceId)
    {
        if (!isset(self::$$property)) {
            if (self::$runtime === null || !self::$runtime->has($serviceId)) {
                return null;
            }

            self::$$property = self::$runtime->get($serviceId);
        }

        return self::$$property;
    }

    /**
     * @var BaseService|null
     */
    protected static ?BaseService $instance = null;

    /**
     * @return bool
     */
    public function hasInstance()
    {
        return self::$instance !== null;
    }

    /**
     * @return BaseService|null
     */
    public function getInstance()
    {
        return self::$instance;
    }

    /**
     * @param $instance
     * @return void
     */
    public function setInstance($instance)
    {
        self::$instance = $instance;
    }

    /**
     * @var string|null
     */
    protected static ?string $projectDir = null;

    /**
     * @param $projectDir
     * @return void
     */
    public static function setProjectDir($projectDir)
    {
        self::$projectDir = $projectDir;
    }

    /**
     * @var string|null
     */
    protected static ?string $environment = null;

    public static function setEnvironment(?string $environment)
    {
        self::$environment = $environment;
    }

    /**
     * @var TranslatorInterface|null
     */
    protected static ?TranslatorInterface $translator = null;

    public static function setTranslator(?TranslatorInterface $translator)
    {
        self::$translator = $translator;
    }

    /**
     * @var RequestStack|null
     */
    protected static ?RequestStack $requestStack = null;

    public static function setRequestStack(RequestStack $requestStack)
    {
        self::$requestStack = $requestStack;
    }

    /**
     * @var Obfuscator|null
     */
    protected static ?Obfuscator $obfuscator = null;

    public static function setObfuscator(Obfuscator $obfuscator)
    {
        self::$obfuscator = $obfuscator;
    }

    /**
     * @var ManagerRegistry|null
     */
    protected static ?ManagerRegistry $doctrine = null;

    public static function setDoctrine(ManagerRegistry $doctrine)
    {
        self::$doctrine = $doctrine;
    }

    /**
     * @var EntityHydratorInterface|null
     */
    protected static ?EntityHydratorInterface $entityHydrator = null;

    public static function setEntityHydrator(EntityHydratorInterface $entityHydrator)
    {
        self::$entityHydrator = $entityHydrator;
    }

    /**
     * @var LocalizerInterface|null
     */
    protected static ?LocalizerInterface $localizer = null;

    public static function setLocalizer(?LocalizerInterface $localizer)
    {
        self::$localizer = $localizer;
    }

    /**
     * @var TradingInterface|null
     */
    protected static ?TradingInterface $tradingMarket = null;

    public static function setTrading(?TradingInterface $tradingMarket)
    {
        self::$tradingMarket = $tradingMarket;
    }

    /**
     * @var TokenStorageInterface|null
     */
    protected static ?TokenStorageInterface $tokenStorage = null;

    public static function setTokenStorage(?TokenStorageInterface $tokenStorage)
    {
        self::$tokenStorage = $tokenStorage;
    }

    /**
     * @var SluggerInterface|null
     */
    protected static ?SluggerInterface $slugger = null;

    public static function setSlugger(?SluggerInterface $slugger)
    {
        self::$slugger = $slugger;
    }

    /**
     * @var IconProvider|null
     */
    protected static ?IconProvider $iconProvider = null;

    public static function setIconProvider(?IconProvider $iconProvider)
    {
        self::$iconProvider = $iconProvider;
    }

    /**
     * @var ClassMetadataManipulator|null
     */
    protected static ?ClassMetadataManipulator $classMetadataManipulator = null;

    public static function setClassMetadataManipulator(?ClassMetadataManipulator $classMetadataManipulator)
    {
        self::$classMetadataManipulator = $classMetadataManipulator;
    }

    /**
     * @var MediaServiceInterface|null
     */
    protected static ?MediaServiceInterface $mediaService = null;

    public static function setMediaService(?MediaServiceInterface $mediaService)
    {
        self::$mediaService = $mediaService;
    }

    /**
     * @var FirewallMapInterface|null
     */
    protected static ?FirewallMapInterface $firewallMap = null;

    public static function setFirewallMap(?FirewallMapInterface $firewallMap)
    {
        self::$firewallMap = $firewallMap;
    }

    /**
     * @var AdvancedRouterInterface|null
     */
    protected static ?AdvancedRouterInterface $router = null;

    public static function setRouter(AdvancedRouterInterface $router)
    {
        self::$router = $router;
    }

    /**
     * @var Environment
     */
    protected static ?Environment $twig = null;

    public static function setTwig(Environment $twig)
    {
        self::$twig = $twig;
    }

    /**
     * @var SettingBag
     */
    protected static ?SettingBag $settings = null;

    public static function setSettingBag(SettingBagInterface $settings)
    {
        self::$settings = $settings;
    }

    /**
     * @var ParameterBagInterface
     */
    protected static ?ParameterBagInterface $parameterBag = null;

    public static function setParameterBag(ParameterBagInterface $parameterBag)
    {
        self::$parameterBag = $parameterBag;
    }

    /**
     * @var NotifierInterface|null
     */
    protected static ?NotifierInterface $notifier = null;

    public static function setNotifier(NotifierInterface $notifier)
    {
        self::$notifier = $notifier;
    }
}
