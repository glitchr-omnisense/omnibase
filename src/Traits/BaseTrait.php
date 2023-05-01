<?php

namespace Base\Traits;

use Base\Annotations\AnnotationReader;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Entity\EntityHydrator;
use Base\Service\Obfuscator;
use Base\Service\ParameterBagInterface;
use Base\Service\TradingMarketInterface;
use Doctrine\ORM\EntityManagerInterface;
use Base\Notifier\Abstract\BaseNotifierInterface;
use Base\Routing\RouterInterface;
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
 *
 */
trait BaseTrait
{
    public static function getAnnotationReader(): ?AnnotationReader
    {
        return AnnotationReader::getInstance();
    }

    public static function getService(): ?BaseService
    {
        return (self::class === BaseService::class) ? BaseService::$instance : BaseService::getService();
    }

    public static function getSettingBag(): ?SettingBag
    {
        return (self::class === BaseService::class) ? BaseService::$settings : BaseService::getSettingBag();
    }

    public static function getDoctrine(): ?ManagerRegistry
    {
        return (self::class === BaseService::class) ? BaseService::$doctrine : BaseService::getDoctrine();
    }

    public static function getObjectManager(mixed $entity): ?ObjectManager
    {
        return (self::class === BaseService::class) ? BaseService::$doctrine->getManagerForClass(is_object($entity) ? get_class($entity) : $entity) : BaseService::getObjectManager($entity);
    }

    public static function getEntityManager(bool $reopen = false): ?EntityManagerInterface
    {
        if (self::class !== BaseService::class) {
            return BaseService::getEntityManager();
        }

        /**
         * @var EntityManager $entityManager
         */
        $entityManager = BaseService::$doctrine->getManager(BaseService::$doctrine->getDefaultManagerName());

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

    public static function getClassMetadataManipulator(): ?ClassMetadataManipulator
    {
        return (self::class === BaseService::class) ? BaseService::$classMetadataManipulator : BaseService::getClassMetadataManipulator();
    }

    public static function getTokenStorage(): ?TokenStorageInterface
    {
        return (self::class === BaseService::class) ? BaseService::$tokenStorage : BaseService::getTokenStorage();
    }

    public static function getRequestStack(): ?RequestStack
    {
        return (self::class === BaseService::class) ? BaseService::$requestStack : BaseService::getRequestStack();
    }

    public static function getEntityHydrator(): ?EntityHydrator
    {
        return (self::class === BaseService::class) ? BaseService::$entityHydrator : BaseService::getEntityHydrator();
    }

    public static function getMediaService(): ?MediaService
    {
        return (self::class === BaseService::class) ? BaseService::$mediaService : BaseService::getMediaService();
    }

    public static function getObfuscator(): ?Obfuscator
    {
        return (self::class === BaseService::class) ? BaseService::$obfuscator : BaseService::getObfuscator();
    }

    public static function getIconProvider(): ?IconProvider
    {
        return (self::class === BaseService::class) ? BaseService::$iconProvider : BaseService::getIconProvider();
    }

    public static function getLocalizer(): ?LocalizerInterface
    {
        return (self::class === BaseService::class) ? BaseService::$localizer : BaseService::getLocalizer();
    }

    public static function getRouter(): ?RouterInterface
    {
        return (self::class === BaseService::class) ? BaseService::$router : BaseService::getRouter();
    }

    public static function getFirewallMap(): ?FirewallMapInterface
    {
        return (self::class === BaseService::class) ? BaseService::$firewallMap : BaseService::getFirewallMap();
    }

    public static function getTwig(): ?Environment
    {
        return (self::class === BaseService::class) ? BaseService::$twig : BaseService::getTwig();
    }

    public static function getNotifier(): ?BaseNotifierInterface
    {
        return (self::class === BaseService::class) ? BaseService::$notifier : BaseService::getNotifier();
    }

    public static function getTranslator(): ?TranslatorInterface
    {
        return (self::class === BaseService::class) ? BaseService::$translator : BaseService::getTranslator();
    }

    public static function getSlugger(): ?SluggerInterface
    {
        return (self::class === BaseService::class) ? BaseService::$slugger : BaseService::getSlugger();
    }

    public static function getTradingMarket(): ?TradingMarketInterface
    {
        return (self::class === BaseService::class) ? BaseService::$tradingMarket : BaseService::getTradingMarket();
    }

    /**
     * @param string $key
     * @param array|null $bag
     * @return array|ParameterBagInterface|bool|float|int|string|\UnitEnum|null
     */
    public static function getParameterBag(string $key = "", ?array $bag = null)
    {
        $parameterBag = self::class === BaseService::class ? BaseService::$parameterBag : BaseService::getParameterBag();
        return $key ? $parameterBag->get($key, $bag) : $parameterBag;
    }
}
