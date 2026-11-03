<?php

namespace Base\Traits;

use Base\Attributes\AttributeReader;
use Base\Contract\Runtime;
use Base\Database\Entity\EntityHydratorInterface;
use Base\Notifier\Abstract\BaseNotifierInterface;
use Base\Service\IconProvider;
use Base\Service\LocalizerInterface;
use Base\Service\Locator;
use Base\Service\MediaService;
use Base\Service\Obfuscator;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBag;
use Base\Service\TradingInterface;
use Base\Service\TranslatorInterface;
use Omnibridge\Contract\Environment as EnvironmentContract;
use Omnibridge\Exception\MissingContractException;

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
 * **Contracts only** (plan "omnibase agnostic", step F2f): every accessor here
 * names a contract - an interface of omnisense/omnibridge or Base\Contract, or
 * a class of the agnostic core (tests/Agnostic.php) -, never a class of
 * Symfony, Doctrine or Twig (tests/Traits/BaseTraitContractsTest.php). Each
 * service comes in two forms:
 * - getX() answers it, or throws Omnibridge\Exception\MissingContractException
 *   when the host has none: for a caller that needs it, which used to fail
 *   further on a call on null;
 * - findX() answers it or null: for a caller that does without (an optional
 *   service, a command without a request, a host that does not answer it).
 *
 * The framework's services it handed out before (the router, Twig, Doctrine's
 * registry and entity manager, the request stack, the token storage, the
 * firewall map, the slugger, the BaseService facade) are in
 * Base\Traits\FrameworkAccessorsTrait, which this trait still uses: deprecated,
 * typed and nullable as they were, removed at 6.0. The core's host code asks
 * them of Base\Bridge\Symfony\FrameworkLocator.
 *
 * An accessor whose answer a contract gives asks the registry first, so it
 * answers wherever the registry is filled, Symfony or not: getParameterBag()
 * (Runtime::parameters()), getEnvironment() (Runtime::environment()->name()),
 * getMediaService() (omnibridge/laravel binds omnibase's MediaService). The
 * others are resolved one at a time, lazily, through the `base.runtime`
 * ServiceLocator seeded in BaseBundle::boot() (BaseCommonTrait::runtimeGet()),
 * or pre-filled when BaseService is built - which nothing does any more on a
 * page's path (BaseService is a lazy service, C9). What they resolve is kept on
 * Base\Service\Locator, framework-free (step R28).
 */
trait BaseTrait
{
    use FrameworkAccessorsTrait;

    public static function findAttributeReader(): ?AttributeReader
    {
        return AttributeReader::getInstance();
    }

    public static function getAttributeReader(): AttributeReader
    {
        return self::findAttributeReader() ?? throw MissingContractException::for(AttributeReader::class);
    }

    public static function findSettingBag(): ?SettingBag
    {
        return Runtime::find(SettingBag::class)
            ?? ((self::class === Locator::class) ? Locator::runtimeGet('settings', 'setting_bag') : Locator::findSettingBag());
    }

    public static function getSettingBag(): SettingBag
    {
        return self::findSettingBag() ?? throw MissingContractException::for(SettingBag::class);
    }

    /**
     * Whether the persistence maps it as an entity (an object, a class, a metadata): Doctrine's manipulator
     * where the host gives it, else the unit of work's shapes for an object or a class (a host without it:
     * omnibridge/laravel; a metadata is Doctrine's, which the manipulator answers).
     */
    public static function isEntity(mixed $entityOrClassOrMetadata): bool
    {
        $manipulator = Locator::runtimeGet('classMetadataManipulator', 'base.database.metadata_manipulator');
        if (null !== $manipulator) {
            return $manipulator->isEntity($entityOrClassOrMetadata);
        }
        $unitOfWork = \Omnibridge\Runtime::find(\Omnibridge\Persistence\UnitOfWork::class);
        if (!$unitOfWork instanceof \Omnibridge\Persistence\UnitOfWork) {
            throw MissingContractException::for('Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator');
        }
        $class = match (true) {
            \is_string($entityOrClassOrMetadata) => $entityOrClassOrMetadata,
            \is_object($entityOrClassOrMetadata) => $unitOfWork->className($entityOrClassOrMetadata),
            default => null,
        };
        if (null === $class || !class_exists($class)) {
            return false;
        }
        try {
            return \Base\Database\UnitOfWorks::shapes($unitOfWork)->has($class);
        } catch (\Throwable) {
            return false;
        }
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

    public static function findEntityHydrator(): ?EntityHydratorInterface
    {
        return Runtime::find(EntityHydratorInterface::class)
            ?? ((self::class === Locator::class) ? Locator::runtimeGet('entityHydrator', 'base.database.entity_hydrator') : Locator::findEntityHydrator());
    }

    public static function getEntityHydrator(): EntityHydratorInterface
    {
        return self::findEntityHydrator() ?? throw MissingContractException::for(EntityHydratorInterface::class);
    }

    public static function findMediaService(): ?MediaService
    {
        // The host's when the registry has it (audit R7: omnibridge/laravel binds omnibase's
        // MediaService with its media on), else the container's, as before.
        $mediaService = Runtime::find(MediaService::class);
        if ($mediaService instanceof MediaService) {
            return $mediaService;
        }

        return (self::class === Locator::class) ? Locator::runtimeGet('mediaService', 'base.service.image') : Locator::findMediaService();
    }

    public static function getMediaService(): MediaService
    {
        return self::findMediaService() ?? throw MissingContractException::for(MediaService::class);
    }

    public static function findObfuscator(): ?Obfuscator
    {
        return Runtime::find(Obfuscator::class)
            ?? ((self::class === Locator::class) ? Locator::runtimeGet('obfuscator', 'obfuscator') : Locator::findObfuscator());
    }

    public static function getObfuscator(): Obfuscator
    {
        return self::findObfuscator() ?? throw MissingContractException::for(Obfuscator::class);
    }

    public static function findIconProvider(): ?IconProvider
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('iconProvider', 'base.service.icon') : Locator::findIconProvider();
    }

    public static function getIconProvider(): IconProvider
    {
        return self::findIconProvider() ?? throw MissingContractException::for(IconProvider::class);
    }

    public static function findLocalizer(): ?LocalizerInterface
    {
        return Runtime::find(LocalizerInterface::class)
            ?? ((self::class === Locator::class) ? Locator::runtimeGet('localizer', 'localizer') : Locator::findLocalizer());
    }

    public static function getLocalizer(): LocalizerInterface
    {
        return self::findLocalizer() ?? throw MissingContractException::for(LocalizerInterface::class);
    }

    public static function findNotifier(): ?BaseNotifierInterface
    {
        return Runtime::find(BaseNotifierInterface::class)
            ?? ((self::class === Locator::class) ? Locator::runtimeGet('notifier', 'base.notifier') : Locator::findNotifier());
    }

    public static function getNotifier(): BaseNotifierInterface
    {
        return self::findNotifier() ?? throw MissingContractException::for(BaseNotifierInterface::class);
    }

    public static function findTranslator(): ?TranslatorInterface
    {
        return Runtime::find(TranslatorInterface::class)
            ?? ((self::class === Locator::class) ? Locator::runtimeGet('translator', 'translator') : Locator::findTranslator());
    }

    public static function getTranslator(): TranslatorInterface
    {
        return self::findTranslator() ?? throw MissingContractException::for(TranslatorInterface::class);
    }

    public static function findTrading(): ?TradingInterface
    {
        return (self::class === Locator::class) ? Locator::runtimeGet('tradingMarket', 'trading_market') : Locator::findTrading();
    }

    public static function getTrading(): TradingInterface
    {
        return self::findTrading() ?? throw MissingContractException::for(TradingInterface::class);
    }

    /**
     * The parameters contract when the registry has it (C9): under Symfony the same
     * `parameter_bag` (the settings stored in the database over the container's).
     */
    public static function findParameterBag(): ?ParameterBagInterface
    {
        return Runtime::find(ParameterBagInterface::class)
            ?? (self::class === Locator::class ? Locator::runtimeGet('parameterBag', 'parameter_bag') : Locator::findParameterBag());
    }

    /**
     * The application's parameters, or one of them by its dotted path (null when it has
     * no such key); MissingContractException when the host has no parameters at all.
     *
     * @return array|ParameterBagInterface|bool|float|int|string|\UnitEnum|null
     */
    public static function getParameterBag(string $key = "", ?array $bag = null)
    {
        $parameterBag = self::findParameterBag() ?? throw MissingContractException::for(ParameterBagInterface::class);

        return $key ? $parameterBag->get($key, $bag) : $parameterBag;
    }
}
