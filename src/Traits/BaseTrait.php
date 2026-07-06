<?php

namespace Base\Traits;

use Base\Attributes\AttributeReader;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Entity\EntityHydrator;
use Base\Service\Obfuscator;
use Base\Service\ParameterBagInterface;
use Base\Service\TradingInterface;
use Doctrine\ORM\EntityManagerInterface;
use Base\Notifier\Abstract\BaseNotifierInterface;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\BaseService;
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
 * subscribers, ...). Backed by BaseService's static properties, which are
 * populated EITHER eagerly by BaseService's constructor (when something
 * actually injects it) OR lazily, one service at a time, through the
 * `base.runtime` ServiceLocator seeded in BaseBundle::boot() — see
 * BaseCommonTrait::runtimeGet(). Nothing here forces BaseService's full
 * dependency graph to be built anymore.
 */
trait BaseTrait
{
    public static function getAttributeReader(): ?AttributeReader
    {
        return AttributeReader::getInstance();
    }

    public static function getService(): ?BaseService
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('instance', 'base.service') : BaseService::getService();
    }

    public static function getSettingBag(): ?SettingBag
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('settings', 'setting_bag') : BaseService::getSettingBag();
    }

    public static function getDoctrine(): ?ManagerRegistry
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('doctrine', 'doctrine') : BaseService::getDoctrine();
    }

    public static function getObjectManager(mixed $entity): ?ObjectManager
    {
        return (self::class === BaseService::class) ? BaseService::getDoctrine()?->getManagerForClass(is_object($entity) ? get_class($entity) : $entity) : BaseService::getObjectManager($entity);
    }

    public static function getEntityManager(bool $reopen = false): ?EntityManagerInterface
    {
        if (self::class !== BaseService::class) {
            return BaseService::getEntityManager();
        }

        $doctrine = BaseService::getDoctrine();
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
        return BaseService::getObjectManager(is_object($object) ? get_class($object) : $object)->getRepository(is_object($object) ? get_class($object) : $object);
    }

    public static function isEntity(mixed $entityOrClassOrMetadata): ?bool
    {
        return BaseService::getClassMetadataManipulator()?->isEntity($entityOrClassOrMetadata);
    }

    public static function getProjectDir(): string
    {
        return (self::class === BaseService::class) ? BaseService::$projectDir : BaseService::getProjectDir();
    }

    public static function getEnvironment(): string
    {
        return (self::class === BaseService::class) ? BaseService::$environment : BaseService::getEnvironment();
    }

    public static function getPublicDir(): string
    {
        return BaseService::getProjectDir() . "/public";
    }

    public static function getTemplateDir(): string
    {
        return BaseService::getProjectDir() . "/templates";
    }

    public static function getTranslationDir(): string
    {
        return BaseService::getProjectDir() . "/translations";
    }

    public static function getCacheDir(): string
    {
        return BaseService::getProjectDir() . "/var/cache/" . BaseService::getEnvironment();
    }

    public static function getLogDir(): string
    {
        return BaseService::getProjectDir() . "/var/log";
    }

    public static function getDataDir(): string
    {
        return BaseService::getProjectDir() . "/data";
    }

    public static function getFixtureDir(): string
    {
        return BaseService::getProjectDir() . "/fixtures";
    }

    public static function getClassMetadataManipulator(): ?ClassMetadataManipulator
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('classMetadataManipulator', 'base.database.metadata_manipulator') : BaseService::getClassMetadataManipulator();
    }

    public static function getTokenStorage(): ?TokenStorageInterface
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('tokenStorage', 'security.token_storage') : BaseService::getTokenStorage();
    }

    public static function getRequestStack(): ?RequestStack
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('requestStack', 'request_stack') : BaseService::getRequestStack();
    }

    public static function getEntityHydrator(): ?EntityHydrator
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('entityHydrator', 'base.database.entity_hydrator') : BaseService::getEntityHydrator();
    }

    public static function getMediaService(): ?MediaService
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('mediaService', 'base.service.image') : BaseService::getMediaService();
    }

    public static function getObfuscator(): ?Obfuscator
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('obfuscator', 'obfuscator') : BaseService::getObfuscator();
    }

    public static function getIconProvider(): ?IconProvider
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('iconProvider', 'base.service.icon') : BaseService::getIconProvider();
    }

    public static function getLocalizer(): ?LocalizerInterface
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('localizer', 'localizer') : BaseService::getLocalizer();
    }

    public static function getRouter(): ?AdvancedRouterInterface
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('router', 'advanced_router') : BaseService::getRouter();
    }

    public static function getFirewallMap(): ?FirewallMapInterface
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('firewallMap', 'security.firewall.map') : BaseService::getFirewallMap();
    }

    public static function getTwig(): ?Environment
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('twig', 'twig') : BaseService::getTwig();
    }

    public static function getNotifier(): ?BaseNotifierInterface
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('notifier', 'base.notifier') : BaseService::getNotifier();
    }

    public static function getTranslator(): ?TranslatorInterface
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('translator', 'translator') : BaseService::getTranslator();
    }

    public static function getSlugger(): ?SluggerInterface
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('slugger', 'slugger') : BaseService::getSlugger();
    }

    public static function getTrading(): ?TradingInterface
    {
        return (self::class === BaseService::class) ? BaseService::runtimeGet('tradingMarket', 'trading_market') : BaseService::getTrading();
    }

    /**
     * @param string $key
     * @param array|null $bag
     * @return array|ParameterBagInterface|bool|float|int|string|\UnitEnum|null
     */
    public static function getParameterBag(string $key = "", ?array $bag = null)
    {
        $parameterBag = self::class === BaseService::class ? BaseService::runtimeGet('parameterBag', 'parameter_bag') : BaseService::getParameterBag();
        return $key ? $parameterBag->get($key, $bag) : $parameterBag;
    }
}
