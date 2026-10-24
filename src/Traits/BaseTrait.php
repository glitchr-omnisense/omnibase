<?php

namespace Base\Traits;

use Base\Attributes\AttributeReader;
use Omnibridge\Contract\Environment as EnvironmentContract;
use Base\Contract\Runtime;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Entity\EntityHydrator;
use Base\Service\Obfuscator;
use Base\Service\ParameterBagInterface;
use Base\Service\TradingInterface;
use Doctrine\ORM\EntityManagerInterface;
use Base\Notifier\Abstract\BaseNotifierInterface;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\BaseService;
use Base\Service\Locator;
use Base\Service\SettingBag;
use Base\Service\IconProvider;
use Base\Service\MediaService;
use Base\Service\LocalizerInterface;
use Base\Service\TranslatorInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Twig\Environment;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Static service accessors usable from anywhere (entities, attributes,
 * subscribers, ...): the tool for writing an application quickly (plan
 * "omnibase agnostique", C9 - Marco: "BaseTrait était un outil qui me
 * permettait de développer rapidement"). It stays, and is not deprecated.
 *
 * The rule: BaseTrait to go fast in an application; the contracts
 * (Base\Contract\Runtime and its interfaces) in the core and the bundles, which
 * add no new use of it (docs/20-architecture/contracts.md).
 *
 * An accessor whose answer a contract gives asks the contract first, so it
 * answers wherever the registry is filled, Symfony or not: getParameterBag()
 * (Runtime::parameters()), getEnvironment() (Runtime::environment()->name()).
 * The others hand out the framework's services, typed as they always were;
 * they are resolved one at a time, lazily, through the `base.runtime`
 * ServiceLocator seeded in BaseBundle::boot() (BaseCommonTrait::runtimeGet()),
 * or pre-filled when BaseService is built - which nothing does any more on a
 * page's path (BaseService is a lazy service, C9). What they resolve is kept on
 * Base\Service\Locator, framework-free (step R28): BaseService, which kept it
 * before and extends it, is no longer loaded by an accessor.
 */
trait BaseTrait
{
    public static function getAttributeReader(): ?AttributeReader
    {
        return AttributeReader::getInstance();
    }

    public static function getService(): ?BaseService
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('instance', 'base.service') : Locator::getService();
    }

    public static function getSettingBag(): ?SettingBag
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('settings', 'setting_bag') : Locator::getSettingBag();
    }

    public static function getDoctrine(): ?ManagerRegistry
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('doctrine', 'doctrine') : Locator::getDoctrine();
    }

    public static function getObjectManager(mixed $entity): ?ObjectManager
    {
        return (self::class === Locator::class) ? Locator::getDoctrine()?->getManagerForClass(is_object($entity) ? get_class($entity) : $entity) : Locator::getObjectManager($entity);
    }

    public static function getEntityManager(bool $reopen = false): ?EntityManagerInterface
    {
        if (self::class !== Locator::class) {
            return Locator::getEntityManager();
        }

        $doctrine = Locator::getDoctrine();
        if (!$doctrine) {
            return null;
        }

        /**
         * @var EntityManager $entityManager
         */
        $entityManager = $doctrine->getManager($doctrine->getDefaultManagerName());

        if (!$entityManager) {
            return null;
        }
        if (!$entityManager->isOpen()) {
            if (!$reopen) {
                return null;
            }
            $entityManager = $entityManager->create(
                $entityManager->getConnection(),
                $entityManager->getConfiguration()
            );
        }

        return $entityManager;
    }

    public static function getRepository(mixed $object = null): ?ObjectRepository
    {
        $object ??= static::class;
        return Locator::getObjectManager(is_object($object) ? get_class($object) : $object)->getRepository(is_object($object) ? get_class($object) : $object);
    }

    public static function isEntity(mixed $entityOrClassOrMetadata): ?bool
    {
        return Locator::getClassMetadataManipulator()?->isEntity($entityOrClassOrMetadata);
    }

    public static function getProjectDir(): string
    {
        return (self::class === Locator::class) ? Locator::$projectDir : Locator::getProjectDir();
    }

    /** The Environment contract's name when the registry has it (C9), else the kernel's, seeded at boot. */
    public static function getEnvironment(): string
    {
        if ($environment = Runtime::find(EnvironmentContract::class)) {
            return $environment->name();
        }

        return (self::class === Locator::class) ? Locator::$environment : Locator::getEnvironment();
    }

    public static function getPublicDir(): string
    {
        return Locator::getProjectDir() . "/public";
    }

    public static function getTemplateDir(): string
    {
        return Locator::getProjectDir() . "/templates";
    }

    public static function getTranslationDir(): string
    {
        return Locator::getProjectDir() . "/translations";
    }

    public static function getCacheDir(): string
    {
        return Locator::getProjectDir() . "/var/cache/" . Locator::getEnvironment();
    }

    public static function getLogDir(): string
    {
        return Locator::getProjectDir() . "/var/log";
    }

    public static function getDataDir(): string
    {
        return Locator::getProjectDir() . "/data";
    }

    public static function getFixtureDir(): string
    {
        return Locator::getProjectDir() . "/fixtures";
    }

    public static function getClassMetadataManipulator(): ?ClassMetadataManipulator
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('classMetadataManipulator', 'base.database.metadata_manipulator') : Locator::getClassMetadataManipulator();
    }

    public static function getTokenStorage(): ?TokenStorageInterface
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('tokenStorage', 'security.token_storage') : Locator::getTokenStorage();
    }

    public static function getRequestStack(): ?RequestStack
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('requestStack', 'request_stack') : Locator::getRequestStack();
    }

    public static function getEntityHydrator(): ?EntityHydrator
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('entityHydrator', 'base.database.entity_hydrator') : Locator::getEntityHydrator();
    }

    public static function getMediaService(): ?MediaService
    {
        // The host's when the registry has it (audit R7: omnibridge/laravel binds omnibase's
        // MediaService with its media on), else the container's, as before.
        $mediaService = Runtime::find(MediaService::class);
        if ($mediaService instanceof MediaService) {
            return $mediaService;
        }

        return (self::class === Locator::class) ? Locator::runtimeGet('mediaService', 'base.service.image') : Locator::getMediaService();
    }

    public static function getObfuscator(): ?Obfuscator
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('obfuscator', 'obfuscator') : Locator::getObfuscator();
    }

    public static function getIconProvider(): ?IconProvider
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('iconProvider', 'base.service.icon') : Locator::getIconProvider();
    }

    public static function getLocalizer(): ?LocalizerInterface
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('localizer', 'localizer') : Locator::getLocalizer();
    }

    public static function getRouter(): ?AdvancedRouterInterface
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('router', 'advanced_router') : Locator::getRouter();
    }

    public static function getFirewallMap(): ?FirewallMapInterface
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('firewallMap', 'security.firewall.map') : Locator::getFirewallMap();
    }

    public static function getTwig(): ?Environment
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('twig', 'twig') : Locator::getTwig();
    }

    public static function getNotifier(): ?BaseNotifierInterface
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('notifier', 'base.notifier') : Locator::getNotifier();
    }

    public static function getTranslator(): ?TranslatorInterface
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('translator', 'translator') : Locator::getTranslator();
    }

    public static function getSlugger(): ?SluggerInterface
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('slugger', 'slugger') : Locator::getSlugger();
    }

    public static function getTrading(): ?TradingInterface
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('tradingMarket', 'trading_market') : Locator::getTrading();
    }

    /**
     * @param string $key
     * @param array|null $bag
     * @return array|ParameterBagInterface|bool|float|int|string|\UnitEnum|null
     */
    public static function getParameterBag(string $key = "", ?array $bag = null)
    {
        // The parameters contract when the registry has it (C9): under Symfony the same
        // `parameter_bag` (the settings stored in the database over the container's).
        $parameterBag = Runtime::find(ParameterBagInterface::class)
            ?? (self::class === Locator::class ? Locator::runtimeGet('parameterBag', 'parameter_bag') : Locator::getParameterBag());

        return $key ? $parameterBag?->get($key, $bag) : $parameterBag;
    }
}
