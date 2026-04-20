<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

use Base\Field\Configurator\LocaleConfigurator;
use Base\Field\Configurator\TextConfigurator;
use Base\Field\Configurator\WysiwygConfigurator;
use Base\Field\Configurator\StockConfigurator;
use Base\Field\Configurator\MoneyConfigurator;
use Base\Field\Configurator\CurrencyConfigurator;
use Base\Field\Configurator\EmailConfigurator;
use Base\Field\Configurator\DateTimePickerConfigurator;
use Base\Field\Configurator\CollectionConfigurator;
use Base\Field\Configurator\ArrayConfigurator;
use Base\Field\Configurator\SlugConfigurator;
use Base\Field\Configurator\CropperConfigurator;
use Base\Field\Configurator\FileConfigurator;
use Base\Field\Configurator\TranslationConfigurator;
use Base\Field\Configurator\BooleanConfigurator;
use Base\Field\Configurator\AssociationConfigurator;
use Base\Field\Configurator\AssociationFileConfigurator;
use Base\Field\Configurator\SelectConfigurator;
use Base\Field\Configurator\RoleConfigurator;
use Base\Field\Configurator\StateConfigurator;
use Base\Field\Configurator\CountryConfigurator;
use Base\Field\Configurator\QuadrantConfigurator;
use Base\Field\Configurator\DiscriminatorConfigurator;
use Base\Field\Configurator\AttributeConfigurator;
use Base\Field\Configurator\IconConfigurator;
use EasyCorp\Bundle\EasyAdminBundle\Intl\IntlFormatter;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->public(false);

    // Form proxy
    $services->set('Base\Form\FormProxy')
        ->public(true)
        ->args([service('form.factory')]);

    // Session storage
    $services->set('Base\Security\Session\DynamicSessionStorageFactory')
        ->public(true)
        ->parent('session.storage.factory.native');

    // Access token handler
    $services->set('Base\Security\AccessTokenHandler')
        ->args([service('Base\Repository\User\TokenRepository')]);

    // Referrer
    $services->set('Base\Service\Referrer')
        ->public(true)
        ->args([
            service('request_stack'),
            service('advanced_router'),
            service('localizer'),
        ]);

    // CacheClearSessionsCommand
    $services->set('Base\Console\Command\CacheClearSessionsCommand')
        ->parent('Base\Console\Command')
        ->public(true)
        ->tag('console.command')
        ->bind('$projectDir', '%kernel.project_dir%');

    // Controllers
    $controllers = [
        'Base\Controller\MainController' => ['base.service', 'setting_bag'],
        'Base\Controller\ProfilerController' => ['base.notifier', 'App\Repository\UserRepository'],
        'Base\Controller\Client\ContactController' => ['form.proxy', 'base.notifier'],
        'Base\Controller\LocalizerController' => ['localizer', 'doctrine.orm.entity_manager', 'advanced_router', 'referrer', 'translator'],
        'Base\Controller\UX\MediaController' => ['request_stack', 'flysystem', 'base.service.image', 'Base\Repository\Layout\ImageCropRepository'],
        'Base\Controller\WidgetController' => ['Base\Repository\Layout\Widget\PageRepository', 'Base\Repository\Layout\Widget\AttachmentRepository'],
        'Base\Controller\ShortLinkController' => ['advanced_router', 'Base\Repository\Layout\ShortLinkRepository'],
    ];

    foreach ($controllers as $id => $args) {
        $definition = $services->set($id)
            ->tag('controller.service_arguments')
            ->tag('container.service_subscriber')
            ->call('setContainer', [service('Psr\Container\ContainerInterface')]);
        foreach ($args as $i => $arg) {
            $definition->arg($i, service($arg));
        }
        if ($id === 'Base\Controller\UX\MediaController') {
            $definition->arg(4, new Reference('profiler', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }
    }

    // Subscribers
    $subscribers = [
        'Base\Subscriber\RouterSubscriber' => ['security.authorization_checker', 'advanced_router', 'parameter_bag', 'setting_bag'],
        'Base\Subscriber\ProfilerSubscriber' => ['advanced_router'],
        'Base\Subscriber\TwigSubscriber' => ['twig.html_renderer', 'twig.webpack_renderer', 'security.authorization_checker', 'parameter_bag', 'advanced_router', '$publicDir' => '%kernel.project_dir%/public'],
        'Base\Subscriber\HotParameterBagSubscriber' => ['parameter_bag', 'setting_bag'],
        'Base\Subscriber\EasyAdminSubscriber' => ['advanced_router', 'EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider', 'EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator'],
        'Base\Subscriber\AdminContextSubscriber' => [],
        'Base\Subscriber\AnalyticsSubscriber' => ['security.token_storage', 'advanced_router', 'translator', 'twig', 'App\Repository\UserRepository', 'ga.service'],
    ];

    foreach ($subscribers as $id => $args) {
        $definition = $services->set($id)
            ->tag('kernel.event_subscriber');
        foreach ($args as $k => $v) {
            if (is_int($k)) {
                $definition->arg($k, service($v));
            } else {
                $definition->bind($k, $v);
            }
        }
    }

    // AdvancedRouter
    $services->set('Base\Routing\AdvancedRouter')
        ->tag('twig.runtime')
        ->args([
            service('router'),
            service('request_stack'),
            service('security.firewall.map'),
            service('parameter_bag'),
            service('localizer'),
            service('twig.extension.assets'),
            service('cache.adapter'),
        ])
        ->bind('$debug', '%kernel.debug%')
        ->bind('$environment', '%kernel.environment%');

    // SettingBag
    $services->alias('Base\Service\SettingBagInterface', 'Base\Service\SettingBag');
    $services->set('Base\Service\SettingBag')
        ->public(true)
        ->tag('twig.runtime')
        ->args([
            service('parameter_bag'),
            service('doctrine.orm.entity_manager'),
            service('Base\Repository\Layout\SettingRepository'),
            service('localizer'),
            service('assets.packages'),
            service('cache.adapter'),
        ])
        ->bind('$environment', '%kernel.environment%');

    // Notifier
    $services->alias('App\Notifier\Notifier', 'Base\Notifier\Notifier');
    $services->alias('Base\Notifier\NotifierInterface', 'Base\Notifier\Notifier');
    $services->set('Base\Notifier\Notifier')
        ->parent('Base\Notifier\Abstract\BaseNotifier')
        ->public(true);

    $services->alias('Base\Notifier\Abstract\BaseNotifierInterface', 'Base\Notifier\Abstract\BaseNotifier');
    $services->set('Base\Notifier\Abstract\BaseNotifier')
        ->abstract(true)
        ->args([
            service('notifier'),
            service('notifier.channel_policy'),
            service('doctrine.orm.entity_manager'),
            service('parameter_bag'),
            service('translator'),
            service('localizer'),
            service('advanced_router'),
            service('twig'),
            service('setting_bag'),
        ])
        ->bind('$debug', '%kernel.debug%');

    // LocalCache / CacheWarmers
    $cacheServices = [
        'Base\Cache\Abstract\AbstractLocalCache' => ['$cacheDir' => '%kernel.cache_dir%'],
        'Base\Cache\Warmer\SpreadsheetCacheWarmer' => ['$cacheDir' => '%kernel.cache_dir%'],
        'Base\Cache\Warmer\IconCacheWarmer' => ['base.service.icon', '$cacheDir' => '%kernel.cache_dir%'],
        'Base\Cache\Warmer\LocalizerCacheWarmer' => ['localizer', '$cacheDir' => '%kernel.cache_dir%'],
        'Base\Cache\Warmer\ThemizerCacheWarmer' => ['themizer', '$cacheDir' => '%kernel.cache_dir%'],
        'Base\Cache\Warmer\WebpackCacheWarmer' => ['parameter_bag', 'twig.webpack_renderer', 'webpack_encore.entrypoint_lookup[_default]', '$cacheDir' => '%kernel.cache_dir%', '$publicDir' => '%kernel.project_dir%/public'],
        'Base\Cache\Warmer\AnnotationCacheWarmer' => ['base.annotation_reader', '$cacheDir' => '%kernel.cache_dir%'],
        'Base\Cache\Warmer\MetadataCacheWarmer' => ['base.database.metadata_manipulator', 'base.annotation_reader', '$cacheDir' => '%kernel.cache_dir%'],
    ];

    foreach ($cacheServices as $id => $args) {
        $definition = $services->set($id);
        if (str_contains($id, 'Warmer')) {
            $definition->tag('kernel.cache_warmer');
        }
        foreach ($args as $k => $v) {
            if (is_int($k)) {
                $definition->arg($k, service($v));
            } else {
                $definition->bind($k, $v);
            }
        }
        if (str_contains($id, 'Warmer')) {
            $definition->public(true);
        }
    }

    // BaseService
    $services->set('Base\Service\BaseService')
        ->public(true)
        ->args([
            service('kernel'),
            service('request_stack'),
            service('security.firewall.map'),
            service('twig'),
            service('slugger'),
            service('doctrine'),
            service('security.authorization_checker'),
            service('security.token_storage'),
            service('security.csrf.token_manager'),
            service('parameter_bag'),
            service('base.notifier'),
            service('form.factory'),
            service('localizer'),
            service('trading_market'),
            service('obfuscator'),
            service('setting_bag'),
            service('base.service.image'),
            service('base.service.icon'),
            service('translator'),
            service('advanced_router'),
            service('base.database.entity_hydrator'),
            service('base.database.metadata_manipulator'),
            service('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator'),
            new Reference('profiler', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

    // Services inheriting from AbstractLocalCache
    $services->set('Base\Service\Localizer')
        ->parent('Base\Cache\Abstract\AbstractLocalCache')
        ->public(true)
        ->tag('twig.runtime')
        ->args([service('parameter_bag'), service('translator')]);

    $services->set('Base\Service\Themizer')
        ->parent('Base\Cache\Abstract\AbstractLocalCache')
        ->public(true)
        ->args([service('parameter_bag'), service('security.token_storage')]);

    $services->set('Base\Cache\SimpleCache')
        ->parent('Base\Cache\Abstract\AbstractLocalCache')
        ->public(true);

    // Maintenance / Launcher / Paginator / Breadgrinder / WidgetProvider
    $services->set('Base\Service\MaintenanceProvider')
        ->public(true)
        ->args([service('advanced_router'), service('setting_bag'), service('security.authorization_checker'), service('parameter_bag'), service('localizer'), service('security.token_storage')]);

    $services->set('Base\Service\Launcher')
        ->public(true)
        ->args([service('advanced_router'), service('parameter_bag'), service('setting_bag'), service('security.authorization_checker'), service('security.token_storage')]);

    $services->set('Base\Service\Paginator')
        ->public(true)
        ->args([service('advanced_router'), service('parameter_bag')]);

    $services->set('Base\Service\Breadgrinder')
        ->public(true)
        ->args([service('advanced_router'), service('translator'), service('parameter_bag')]);

    $services->set('Base\Service\WidgetProvider')
        ->public(true)
        ->tag('twig.runtime')
        ->args([service('doctrine.orm.entity_manager')]);

    // Form extensions
    $services->set('Base\Form\Extension\FormTypeTranslateExtension')->tag('form.type_extension');

    $services->set('Base\Form\Extension\FormTypeSpamExtension')
        ->tag('form.type_extension')
        ->args([service('spam_checker'), service('advanced_router')]);

    // Console
    $services->set('Base\Console\Console')->public(true)->args([service('kernel')]);
    $services->set('Base\Console\Command')->public(true)->args([service('localizer'), service('translator'), service('doctrine.orm.entity_manager'), service('parameter_bag')]);

    $commandServices = [
        'Base\Console\Command\NotifierCommand',
        'Base\Console\Command\DoctrineSchemaCharsetCommand',
        'Base\Console\Command\DoctrineArrayUpgradeCommand',
        'Base\Console\Command\BaseMappingCommand',
        'Base\Console\Command\ThreadPublishableCommand',
        'Base\Console\Command\EntityDiscriminatorCommand',
        'Base\Console\Command\NotifierMailTestCommand',
        'Base\Console\Command\TimeMachineSnapshotCommand',
        'Base\Console\Command\TimeMachineSnapshotBackupCommand',
        'Base\Console\Command\TimeMachineSnapshotRestoreCommand',
    ];

    foreach ($commandServices as $command) {
        $definition = $services->set($command)
            ->parent('Base\Console\Command')
            ->tag('console.command');
        // Add specific arguments for certain commands
        if ($command === 'Base\Console\Command\EntityDiscriminatorCommand') {
            $definition->arg(0, service('base.database.metadata_manipulator'));
        }
        if ($command === 'Base\Console\Command\NotifierMailTestCommand') {
            $definition->args([service('base.notifier'), service('App\Repository\UserRepository')]);
        }
        if (str_contains($command, 'TimeMachineSnapshot')) {
            $definition->args([service('time_machine'), service('flysystem')]);
        }
    }

    // TimeMachine service
    $services->set('Base\Service\TimeMachine')
        ->parent('Backup\Manager\Manager')
        ->public(true)
        ->args([service('flysystem'), service('doctrine'), service('parameter_bag')]);

    // Console commands
    $services->set('Base\Console\Command\UserNotificationCommand')
        ->parent('Base\Console\Command')
        ->tag('console.command');

    $services->set('Base\Console\Command\UploaderEntitiesCommand')
        ->parent('Base\Console\Command')
        ->tag('console.command');

    $services->set('Base\Console\Command\UploaderImagesCommand')
        ->parent('Base\Console\Command\UploaderEntitiesCommand')
        ->tag('console.command')
        ->args([
            new Reference('base.service.image'),
            new Reference('Base\Controller\UX\MediaController'),
        ]);

    $services->set('Base\Console\Command\UploaderImagesCropCommand')
        ->parent('Base\Console\Command\UploaderImagesCommand')
        ->tag('console.command');

    $services->set('Base\Console\Command\TranslationControllersCommand')
        ->parent('Base\Console\Command')
        ->tag('console.command');

    $services->set('Base\Console\Command\TranslationSettingsCommand')
        ->parent('Base\Console\Command')
        ->tag('console.command')
        ->args([new Reference('setting_bag')]);

    $services->set('Base\Console\Command\IconEntitiesCommand')
        ->parent('Base\Console\Command')
        ->tag('console.command')
        ->args([new Reference('base.database.metadata_manipulator')]);

    $services->set('Base\Console\Command\DoctrineDatabaseImportCommand')
        ->parent('Base\Console\Command')
        ->tag('console.command')
        ->args([
            new Reference('base.database.entity_hydrator'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('base.notifier'),
        ]);

    // Controllers
    $services->set('Base\Controller\ErrorController')
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')])
        ->args([
            new Reference('error_handler.error_renderer.html'),
            new Reference('advanced_router'),
            new Reference('base.service'),
            new Reference('profiler', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ]);

    $services->set('Base\Controller\SitemapController')
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')]);

    $services->set('Base\Controller\RescueController')
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')])
        ->args([
            new Reference('advanced_router'),
            new Reference('base.service.image'),
            new Reference('setting_bag'),
            new Reference('twig'),
            new Reference('translator'),
            new Reference('form.proxy'),
        ]);

    $services->set('Base\Controller\SecurityController')
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')])
        ->args([
            new Reference('base.notifier'),
            new Reference('doctrine.orm.entity_manager'),
            new Reference('Base\Repository\User\TokenRepository'),
            new Reference('App\Repository\UserRepository'),
            new Reference('advanced_router'),
            new Reference('form.proxy'),
            new Reference('security.token_storage'),
            new Reference('translator'),
            new Reference('parameter_bag'),
        ]);

    // Subscribers
    $services->set('Base\Subscriber\LocalizerSubscriber')
        ->tag('kernel.event_subscriber')
        ->args([
            new Reference('localizer'),
            new Reference('advanced_router'),
            new Reference('security.token_storage'),
        ]);

    $services->set('Base\Subscriber\EagerSubscriber')
        ->tag('kernel.event_subscriber')
        ->args([new Reference('base.service')]);

    $services->set('Base\Subscriber\FlashBagSubscriber')
        ->tag('kernel.event_subscriber');

    // Database Subscribers
    $services->set('Base\DatabaseSubscriber\EnumSubscriber'); // compiler pass

    $services->set('Base\Database\Middleware\PlatformMiddleware')
        ->tag('doctrine.middleware');

    $services->set('Base\DatabaseSubscriber\AnnotationSubscriber')
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata', 'priority' => 4096])
        ->tag('doctrine.event_listener', ['event' => 'resolveDiscriminator', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'preQuery', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'onQuery', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'postQuery', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'postLoad', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'preFlush', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'onFlush', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'postFlush', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'prePersist', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'preUpdate', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'preRemove', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'postPersist', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'postUpdate', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'postRemove', 'priority' => 2048])
        ->args([
            new Reference('doctrine.orm.entity_manager'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('base.annotation_reader'),
        ]);

    /* ------------------------------
    * Doctrine Subscribers
    * ------------------------------*/

    $services->set('Base\DatabaseSubscriber\IntlSubscriber')
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata',    'priority' => 4096])
        ->tag('doctrine.event_listener', ['event' => 'resolveDiscriminator', 'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'onQuery',              'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'postLoad',             'priority' => 2048])
        ->tag('doctrine.event_listener', ['event' => 'onFlush',              'priority' => 2048])
        ->args([
            new Reference('doctrine.orm.entity_manager'),
            new Reference('localizer'),
        ]);

    $services->set('Base\DatabaseSubscriber\TrackingPolicySubscriber')
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata'])
        ->args([new Reference('base.database.metadata_manipulator')]);

    $services->set('Base\EntitySubscriber\ExtensionSubscriber')
        ->tag('doctrine.event_listener', ['event' => 'onFlush'])
        ->tag('doctrine.event_listener', ['event' => 'postPersist'])
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata'])
        ->args([
            new Reference('doctrine.orm.entity_manager'),
            new Reference('base.entity_extension'),
        ]);


    /* ------------------------------
    * Core Services
    * ------------------------------*/

    $services->set('Base\Service\SpamChecker')
        ->public()
        ->args([
            new Reference('request_stack'),
            new Reference('setting_bag'),
            new Reference('parameter_bag'),
            new Reference('translator'),
            new Reference('monolog.http_client'),
        ])
        ->bind('$debug', '%kernel.debug%');

    $services->set('Base\Service\Sitemapper')
        ->public()
        ->args([
            new Reference('twig'),
            new Reference('base.annotation_reader'),
            new Reference('advanced_router'),
            new Reference('localizer'),
        ]);


    /* ------------------------------
    * WYSIWYG Enhancers
    * ------------------------------*/

    $services->set('Base\Service\Model\Wysiwyg\SemanticEnhancer')
        ->public()
        ->tag('twig.runtime')
        ->args([new Reference('Base\Repository\Layout\SemanticRepository')]);

    $services->set('Base\Service\Model\Wysiwyg\MentionEnhancer')
        ->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('App\Repository\Thread\MentionRepository'),
            new Reference('App\Repository\UserRepository'),
            new Reference('doctrine.orm.entity_manager'),
            new Reference('obfuscator'),
        ]);

    $services->set('Base\Service\Model\Wysiwyg\MediaEnhancer')
        ->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('base.service.image'),
            new Reference('flysystem'),
        ]);

    $services->set('Base\Service\Model\Wysiwyg\HeadingEnhancer')
        ->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('doctrine.orm.entity_manager'),
            new Reference('slugger'),
        ]);

    $services->set('Base\Service\WysiwygEnhancer')
        ->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('twig'),
            new Reference('heading_enhancer'),
            new Reference('semantic_enhancer'),
            new Reference('mention_enhancer'),
            new Reference('media_enhancer'),
        ]);

    $services->set('Base\Service\EditorEnhancer')
        ->parent('Base\Service\WysiwygEnhancer')
        ->public()
        ->tag('twig.runtime');


    /* ------------------------------
    * Obfuscator System
    * ------------------------------*/

    $services->set('Base\Service\Model\Obfuscator\AbstractCompression')->abstract();

    foreach ([
        'NullCompression',
        'ZlibCompression',
        'GzipCompression',
        'DeflateCompression',
    ] as $class) {
        $services->set("Base\Service\Model\Obfuscator\Compression\\$class")
            ->parent('Base\Service\Model\Obfuscator\AbstractCompression')
            ->tag('obfuscator.compression');
    }

    $services->set('Base\Service\Model\Obfuscator\Compression\HashidsCompression')
        ->parent('Base\Service\Model\Obfuscator\AbstractCompression')
        ->tag('obfuscator.compression')
        ->bind('$secret', '%kernel.secret%');

    $services->set('Base\Service\Obfuscator')
        ->public()
        ->tag('twig.runtime')
        ->args([new Reference('parameter_bag')])
        ->bind('$cacheDir', '%kernel.cache_dir%');


    /* ------------------------------
    * File & Media Services
    * ------------------------------*/

    $services->set('Base\Service\FileService')
        ->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('twig'),
            new Reference('advanced_router'),
            new Reference('obfuscator'),
            new Reference('flysystem'),
            new Reference('parameter_bag'),
        ]);

    $services->set('Base\Service\MediaService')
            ->parent('Base\Service\FileService')
            ->public()
            ->tag('twig.runtime')
            ->args([
                new Reference('imagine.bitmap'),
                new Reference('imagine.svg'),
                new Reference('profiler', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            ]);

    $services->set('Base\Service\Flysystem')
        ->parent('flysystem.adapter.lazy.factory')
        ->public();


    /* ------------------------------
    * Imagine Adapters
    * ------------------------------*/

    $services->alias('imagine.bitmap', 'imagine.adapter.imagick');
    $services->alias('imagine.svg', 'imagine.adapter.svg');

    $services->set('imagine.meta_data.reader', 'Imagine\Image\Metadata\ExifMetadataReader');

    foreach ([
        'gd'      => 'Imagine\Gd\Imagine',
        'imagick' => 'Imagine\Imagick\Imagine',
        'gmagick' => 'Imagine\Gmagick\Imagine',
        'svg'     => 'Base\Imagine\Svg\Imagine',
    ] as $id => $class) {
        $services->set("imagine.adapter.$id", $class)
            ->call('setMetadataReader', [new Reference('imagine.meta_data.reader')]);
    }


    /* ------------------------------
    * Sharing System
    * ------------------------------*/

    $services->set('Base\Service\Sharer')->public()->tag('twig.runtime');

    $services->set('Base\Service\Model\Sharer\AbstractSharerAdapter')
        ->args([new Reference('twig')]);

    foreach ([
        'FacebookAdapter',
        'LinkedInAdapter',
        'GooglePlusAdapter',
        'TumblrAdapter',
        'TwitterAdapter',
        'PinterestAdapter',
    ] as $adapter) {
        $services->set("Base\Service\Model\Sharer\Adapter\\$adapter")
            ->parent('Base\Service\Model\Sharer\AbstractSharerAdapter')
            ->tag('base.service.sharer');
    }


    /* ------------------------------
    * Icon System
    * ------------------------------*/

    $services->set('Base\Service\IconProvider')
        ->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('base.annotation_reader'),
            new Reference('base.service.image'),
            new Reference('localizer'),
            new Reference('advanced_router'),
        ])
        ->bind('$cacheDir', '%kernel.cache_dir%');

    $services->set('Base\Service\Model\IconProvider\Adapter\FontAwesomeAdapter')
        ->tag('base.service.icon')
        ->bind('$metadata', '%kernel.project_dir%/public/bundles/base/metadata/icons.yml')
        ->bind('$cacheDir', '%kernel.cache_dir%');

    $services->set('Base\Service\Model\IconProvider\Adapter\BootstrapTwitterAdapter')
        ->tag('base.service.icon')
        ->bind('$metadata', '%kernel.project_dir%/public/bundles/base/metadata/bootstrap-icons.json')
        ->bind('$cacheDir', '%kernel.cache_dir%');


    /* ------------------------------
    * Trading & Currency
    * ------------------------------*/

    $services->set('Base\Service\Trading')
        ->args([new Reference('http_client')])
        ->bind('$cacheDir', '%kernel.cache_dir%');

    $services->set('Base\Service\Model\Currency\AbstractCurrencyApi')
        ->args([new Reference('setting_bag')]);

    foreach ([
        'Fixer',
        'AbstractApi',
        'ExchangeRatesApi',
        'CurrencyLayer',
    ] as $api) {
        $services->set("Base\Service\Model\Currency\Api\\$api")
            ->parent('Base\Service\Model\Currency\AbstractCurrencyApi')
            ->tag('currency.api');
    }
    // Database services
    $services->set('Base\Database\Mapping\NamingStrategy')->public();
    $services->set('Base\Database\Mapping\ClassMetadataFactory')->public();

    $services->set('Base\Database\Entity\EntityHydrator')->public()
        ->args([
            new Reference('doctrine.orm.entity_manager'),
            new Reference('base.database.metadata_manipulator'),
        ]);

    $services->set('Base\Database\Mapping\ClassMetadataManipulator')->public()
        ->args([
            new Reference('doctrine'),
            new Reference('doctrine.orm.entity_manager'),
        ])
        ->bind('$cacheDir', '%kernel.cache_dir%');

    // Annotation reader
    $services->set('Base\Annotations\AnnotationReader')->public()
        ->args([
            new Reference('event_dispatcher'),
            new Reference('advanced_router'),
            new Reference('doctrine.orm.entity_manager'),
            new Reference('parameter_bag'),
            new Reference('flysystem'),
            new Reference('request_stack'),
            new Reference('security.token_storage'),
            new Reference('base.database.entity_hydrator'),
            new Reference('base.database.metadata_manipulator'),
        ])
        ->bind('$projectDir', '%kernel.project_dir%')
        ->bind('$environment', '%kernel.environment%')
        ->bind('$cacheDir', '%kernel.cache_dir%');

    // Security voters
    $services->set('Base\Security\Voter\AdminVoter')
        ->tag('security.voter')
        ->args([new Reference('advanced_router')]);

    $services->set('Base\Security\Voter\AccessVoter')
        ->tag('security.voter')
        ->args([
            new Reference('request_stack'),
            new Reference('advanced_router'),
            new Reference('setting_bag'),
            new Reference('parameter_bag'),
            new Reference('security.firewall.map'),
            new Reference('localizer'),
            new Reference('base.service.maintenance'),
            new Reference('base.service.launcher'),
        ]);

    $services->set('Base\Database\Entity\EntityExtension')->public();

    // Twig services
    $services->set('Base\Twig\AssetPackage')->public()
        ->tag('assets.package', ['package' => 'base.assets.package'])
        ->args([new Reference('request_stack')]);

    $services->set('Base\Twig\Variable\SiteVariable')->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('advanced_router'),
            new Reference('sitemap'),
            new Reference('translator'),
            new Reference('localizer'),
            new Reference('base.service'),
            new Reference('base.service.maintenance'),
            new Reference('base.service.launcher'),
        ]);

    $services->set('Base\Twig\Variable\EmailVariable')->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('base.service'),
            new Reference('base.service.launcher'),
        ]);

    $services->set('Base\Twig\Variable\RandomVariable')->public()
        ->tag('twig.variable');

    $services->set('Base\Twig\Variable\AdminVariable')->public()
        ->parent('Base\Twig\Variable\SiteVariable')
        ->tag('twig.runtime')
        ->tag('twig.variable')
        ->args([new Reference('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator')]);

    $services->set('Base\Twig\Extension\MediaTwigExtension')->public()
        ->tag('twig.extension')
        ->args([
            new Reference('advanced_router'),
            new Reference('base.service.image'),
            new Reference('Base\Controller\UX\MediaController'),
        ])
        ->bind('$projectDir', '%kernel.project_dir%');

    $services->set('Base\Twig\Extension\SemanticTwigExtension')->public()
        ->tag('twig.extension')
        ->args([new Reference('semantic_enhancer')]);

    $services->set('Base\Twig\Extension\TradingTwigExtension')->public()
        ->tag('twig.extension')
        ->args([new Reference('trading_market')]);

    // Twig renderer adapters
    $services->set('Base\Twig\Renderer\AbstractTagRenderer')
        ->abstract()
        ->args([
            new Reference('twig'),
            new Reference('localizer'),
            new Reference('slugger'),
            new Reference('parameter_bag'),
        ]);

    $services->set('Base\Twig\Renderer\Adapter\HtmlTagRenderer')->public()
        ->parent('Base\Twig\Renderer\AbstractTagRenderer')
        ->tag('twig.runtime')
        ->tag('twig.tag_renderer')
        ->args([
            new Reference('request_stack'),
            new Reference('advanced_router'),
        ]);

    $services->set('Base\Twig\Renderer\Adapter\WebpackTagRenderer')->public()
        ->parent('Base\Twig\Renderer\AbstractTagRenderer')
        ->tag('twig.runtime')
        ->tag('twig.tag_renderer')
        ->args([
            new Reference('webpack_encore.entrypoint_lookup_collection', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('assets.packages'),
        ])
        ->bind('$publicDir', '%kernel.project_dir%/public')
        ->bind('$cacheDir', '%kernel.cache_dir%');

    $services->set('Base\Twig\Extension\WebpackTwigExtension')
        ->tag('twig.extension', ['priority' => -1]);

    $services->set('Base\Twig\Extension\HtmlTwigExtension')
        ->tag('twig.extension')
        ->args([
            new Reference('twig.html_renderer'),
            new Reference('wysiwyg_enhancer'),
            new Reference('editor_enhancer'),
        ]);

    $services->set('Base\Twig\Extension\FunctionTwigExtension')->public()
        ->tag('twig.extension')
        ->args([
            new Reference('translator'),
            new Reference('twig.extension.assets'),
            new Reference('twig'),
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator'),
        ])
        ->bind('$projectDir', '%kernel.project_dir%');

    $services->set('Base\Twig\Extension\MathTwigExtension')->tag('twig.extension');
    $services->set('Base\Twig\Extension\ClassTwigExtension')->tag('twig.extension');
    $services->set('Base\Twig\Extension\ClipboardTwigExtension')->tag('twig.extension');
    $services->set('Base\Twig\Extension\FormTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('form.proxy')]);
    $services->set('Base\Twig\Extension\WidgetTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('base.widget_provider')]);
    $services->set('Base\Twig\Extension\LocalizerTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('localizer')]);
    $services->set('Base\Twig\Extension\ThemizerTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('themizer')]);
    $services->set('Base\Twig\Extension\TranslatorTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('localizer')]);
    $services->set('Base\Twig\Extension\PaginatorTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('translator')]);
    $services->set('Base\Twig\Extension\BreadgrinderTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('base.breadgrinder')]);
    $services->set('Base\Twig\Extension\ShareTwigExtension')
        ->tag('twig.extension')
        ->args([new Reference('base.service.sharer')]);

    // Form extensions
    $services->set('Base\Form\Extension\EaCrudFormCompatExtension')->tag('form.type_extension');
    $services->set('Base\Form\Extension\FormTypeBootstrapExtension')
        ->tag('form.type_extension')
        ->args([new Reference('base.service')]);
    $services->set('Base\Form\Extension\FormTypeCsrfExtension')->tag('form.type_extension');
    $services->set('Base\Form\Extension\FormTypeExtension')
        ->tag('form.type_extension')
        ->args([
            new Reference('advanced_router'),
            new Reference('security.authorization_checker'),
            new Reference('parameter_bag'),
            new Reference('form.factory'),
            new Reference('form.proxy'),
            new Reference('base.database.metadata_manipulator'),
        ]);
    $services->set('Base\Form\Extension\FormTypeWebpackExtension')
        ->tag('form.type_extension')
        ->args([
            new Reference('form.proxy'),
            new Reference('twig.webpack_renderer'),
        ]);
    $services->set('Base\Form\Extension\FormTypeCollectionExtension')
        ->tag('form.type_extension')
        ->args([new Reference('base.database.metadata_manipulator')]);

    // Validators
    $services->set('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator')
        ->args([new Reference('translator')]);

    $services->set('Base\Validator\Constraints\AlphanumericValidator')
        ->parent('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator');

    $services->set('Base\Validator\Constraints\AlphanumericPlusValidator')
        ->parent('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator');

    $services->set('Base\Validator\Constraints\FileValidator')
        ->parent('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator');

    $services->set('Base\Validator\Constraints\HexcodeValidator')
        ->parent('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator');

    $services->set('Base\Validator\Constraints\NotBlankValidator')
        ->parent('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator');

    $services->set('Base\Validator\ConstraintEntityValidator')
        ->parent('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator');

    $services->set('Base\Validator\Constraints\UniqueEntityValidator')
        ->parent('Base\Validator\ConstraintEntityValidator')
        ->tag('validator.constraint_validator');

    $services->set('Base\Validator\Constraints\StringCaseEntityValidator')
        ->parent('Base\Validator\ConstraintEntityValidator')
        ->tag('validator.constraint_validator');

$services->set('Base\Database\Mapping\NamingStrategy')->public();
    $services->set('Base\Database\Mapping\ClassMetadataFactory')->public();

    $services->set('Base\Database\Entity\EntityHydrator')
        ->public()
        ->args([
            new Reference('doctrine.orm.entity_manager'),
            new Reference('base.database.metadata_manipulator')
        ]);

    $services->set('Base\Database\Mapping\ClassMetadataManipulator')
        ->public()
        ->args([
            new Reference('doctrine'),
            new Reference('doctrine.orm.entity_manager')
        ])
        ->bind('$cacheDir', '%kernel.cache_dir%');

    $services->set('Base\Annotations\AnnotationReader')
        ->public()
        ->args([
            new Reference('event_dispatcher'),
            new Reference('advanced_router'),
            new Reference('doctrine.orm.entity_manager'),
            new Reference('parameter_bag'),
            new Reference('flysystem'),
            new Reference('request_stack'),
            new Reference('security.token_storage'),
            new Reference('base.database.entity_hydrator'),
            new Reference('base.database.metadata_manipulator')
        ])
        ->bind('$projectDir', '%kernel.project_dir%')
        ->bind('$environment', '%kernel.environment%')
        ->bind('$cacheDir', '%kernel.cache_dir%');

    $services->set('Base\Security\Voter\AdminVoter')
        ->tag('security.voter')
        ->args([new Reference('advanced_router')]);

    $services->set('Base\Security\Voter\AccessVoter')
        ->tag('security.voter')
        ->args([
            new Reference('request_stack'),
            new Reference('advanced_router'),
            new Reference('setting_bag'),
            new Reference('parameter_bag'),
            new Reference('security.firewall.map'),
            new Reference('localizer'),
            new Reference('base.service.maintenance'),
            new Reference('base.service.launcher')
        ]);

    $services->set('Base\Database\Entity\EntityExtension')->public();

    // ------------------------------
    // Twig Variables & Extensions
    // ------------------------------
    $services->set('Base\Twig\AssetPackage')->public()
        ->tag('assets.package', ['package' => 'base.assets.package'])
        ->args([new Reference('request_stack')]);

    $services->set('Base\Twig\Variable\SiteVariable')->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('advanced_router'),
            new Reference('sitemap'),
            new Reference('translator'),
            new Reference('localizer'),
            new Reference('base.service'),
            new Reference('base.service.maintenance'),
            new Reference('base.service.launcher')
        ]);

    $services->set('Base\Twig\Variable\EmailVariable')->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('base.service'),
            new Reference('base.service.launcher')
        ]);

    $services->set('Base\Twig\Variable\RandomVariable')->public()
        ->tag('twig.variable');

    $services->set('Base\Twig\Variable\AdminVariable')
        ->parent('Base\Twig\Variable\SiteVariable')
        ->public()
        ->tag('twig.runtime')
        ->tag('twig.variable')
        ->args([new Reference('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator')]);

    $services->set('Base\Twig\Extension\MediaTwigExtension')->public()
        ->tag('twig.extension')
        ->args([
            new Reference('advanced_router'),
            new Reference('base.service.image'),
            new Reference('Base\Controller\UX\MediaController')
        ])
        ->bind('$projectDir', '%kernel.project_dir%');

    $services->set('Base\Twig\Extension\SemanticTwigExtension')->public()
        ->tag('twig.extension')
        ->args([new Reference('semantic_enhancer')]);

    $services->set('Base\Twig\Extension\TradingTwigExtension')->public()
        ->tag('twig.extension')
        ->args([new Reference('trading_market')]);

    $services->set('Base\Twig\Renderer\AbstractTagRenderer')
        ->abstract()
        ->args([
            new Reference('twig'),
            new Reference('localizer'),
            new Reference('slugger'),
            new Reference('parameter_bag')
        ]);

    $services->set('Base\Twig\Renderer\Adapter\HtmlTagRenderer')
        ->parent('Base\Twig\Renderer\AbstractTagRenderer')
        ->public()
        ->tag('twig.runtime')
        ->tag('twig.tag_renderer')
        ->args([
            new Reference('request_stack'),
            new Reference('advanced_router')
        ]);

    $services->set('Base\Twig\Renderer\Adapter\WebpackTagRenderer')
        ->parent('Base\Twig\Renderer\AbstractTagRenderer')
        ->public()
        ->tag('twig.runtime')
        ->tag('twig.tag_renderer')
        ->args([
            new Reference('webpack_encore.entrypoint_lookup_collection', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('assets.packages')
        ])
        ->bind('$publicDir', '%kernel.project_dir%/public')
        ->bind('$cacheDir', '%kernel.cache_dir%');

    $services->set('Base\Twig\Extension\WebpackTwigExtension')
        ->tag('twig.extension', ['priority' => -1]);

    $services->set('Base\Twig\Extension\HtmlTwigExtension')
        ->tag('twig.extension')
        ->args([
            new Reference('twig.html_renderer'),
            new Reference('wysiwyg_enhancer'),
            new Reference('editor_enhancer')
        ]);

    $services->set('Base\Twig\Extension\FunctionTwigExtension')->public()
        ->tag('twig.extension')
        ->args([
            new Reference('translator'),
            new Reference('twig.extension.assets'),
            new Reference('twig'),
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator')
        ])
        ->bind('$projectDir', '%kernel.project_dir%');

    // ------------------------------
    // Form Extensions
    // ------------------------------
    $services->set('Base\Form\Extension\EaCrudFormCompatExtension')->tag('form.type_extension');

    $services->set('Base\Form\Extension\FormTypeBootstrapExtension')
        ->tag('form.type_extension')
        ->args([new Reference('base.service')]);

    $services->set('Base\Form\Extension\FormTypeCsrfExtension')
        ->tag('form.type_extension');

    $services->set('Base\Form\Extension\FormTypeExtension')
        ->tag('form.type_extension')
        ->args([
            new Reference('advanced_router'),
            new Reference('security.authorization_checker'),
            new Reference('parameter_bag'),
            new Reference('form.factory'),
            new Reference('form.proxy'),
            new Reference('base.database.metadata_manipulator')
        ]);

    $services->set('Base\Form\Extension\FormTypeWebpackExtension')
        ->tag('form.type_extension')
        ->args([
            new Reference('form.proxy'),
            new Reference('twig.webpack_renderer')
        ]);

    $services->set('Base\Form\Extension\FormTypeCollectionExtension')
        ->tag('form.type_extension')
        ->args([new Reference('base.database.metadata_manipulator')]);

    // ------------------------------
    // Validators
    // ------------------------------
    $services->set('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator')
        ->args([new Reference('translator')]);

    $validatorChildren = [
        'Base\Validator\Constraints\AlphanumericValidator',
        'Base\Validator\Constraints\AlphanumericPlusValidator',
        'Base\Validator\Constraints\FileValidator',
        'Base\Validator\Constraints\HexcodeValidator',
        'Base\Validator\Constraints\NotBlankValidator',
    ];

    foreach ($validatorChildren as $child) {
        $services->set($child)->parent('Base\Validator\ConstraintValidator')
            ->tag('validator.constraint_validator');
    }

    $services->set('Base\Validator\ConstraintEntityValidator')
        ->parent('Base\Validator\ConstraintValidator')
        ->tag('validator.constraint_validator');

    $entityValidators = [
        'Base\Validator\Constraints\UniqueEntityValidator',
        'Base\Validator\Constraints\StringCaseEntityValidator',
    ];

    foreach ($entityValidators as $child) {
        $services->set($child)->parent('Base\Validator\ConstraintEntityValidator')
            ->tag('validator.constraint_validator');
    }

    // ------------------------------
    // Notifier channels
    // ------------------------------
    $services->set('Base\Notifier\Channel\BrowserPlusChannel')
        ->tag('notifier.channel', ['channel' => 'browser+'])
        ->args([new Reference('request_stack')]);

    $services->set('Base\Notifier\Channel\EmailPlusChannel')
        ->parent('notifier.channel.email')
        ->tag('notifier.channel', ['channel' => 'email+']);

    // ------------------------------
    // Controllers, Subscribers, Security
    // ------------------------------
    $controllerServices = [
        'Base\Controller\Client\ThreadSearchController',
        'Base\Controller\Client\UserSearchController',
        'Base\Controller\Client\UserProfileController',
        'Base\Controller\Client\UserSettingsController',
    ];

    foreach ($controllerServices as $controller) {
        $services->set($controller)
            ->autowire(true)
            ->autoconfigure(true)
            ->tag('controller.service_arguments')
            ->tag('container.service_subscriber')
            ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')]);
    }

    $services->set('Base\Security\UserTracker')
        ->args([
            new Reference('doctrine.orm.entity_manager'),
            new Reference('request_stack'),
            new Reference('advanced_router'),
            new Reference('Base\Repository\User\ConnectionRepository')
        ]);

    $services->set('Base\EntitySubscriber\ConnectionSubscriber')
        ->tag('kernel.event_subscriber')
        ->args([
            new Reference('Base\Security\UserTracker'),
            new Reference('doctrine.orm.entity_manager')
        ]);

    $services->set('Base\Security\UserChecker')
        ->args([new Reference('doctrine.orm.entity_manager')]);

    $services->set('Base\Security\UserProvider')
        ->args([new Reference('Base\Security\UserTracker')]);

    $services->set('Base\Security\RescueFormAuthenticator')
        ->parent('Base\Security\LoginFormAuthenticator');

    $services->set('Base\Security\LoginFormAuthenticator')
        ->args([
            new Reference('referrer'),
            new Reference('doctrine.orm.entity_manager'),
            new Reference('advanced_router'),
            new Reference('security.authorization_checker')
        ]);

    $subscriberServices = [
        'Base\Subscriber\NotifierSubscriber' => [
            'args' => [
                new Reference('base.notifier'),
                new Reference('security.authorization_checker'),
                new Reference('parameter_bag')
            ],
            'bind' => ['$debug' => '%kernel.debug%']
        ],
        'Base\Subscriber\IsGrantedSubscriber' => [
            'args' => [
                new Reference('base.annotation_reader'),
                new Reference('security.token_storage'),
                new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE)
            ]
        ],
        'Base\Subscriber\SecuritySubscriber' => [
            'args' => [
                new Reference('App\Repository\UserRepository'),
                new Reference('security.authorization_checker'),
                new Reference('security.token_storage'),
                new Reference('request_stack'),
                new Reference('referrer'),
                new Reference('setting_bag'),
                new Reference('localizer'),
                new Reference('advanced_router'),
                new Reference('parameter_bag'),
                new Reference('base.service.maintenance'),
                new Reference('base.service.launcher'),
                new Reference('profiler', ContainerInterface::NULL_ON_INVALID_REFERENCE)
            ]
        ],
        'Base\Subscriber\ReferrerSubscriber' => [
            'args' => [
                new Reference('referrer'),
                new Reference('advanced_router'),
                new Reference('parameter_bag')
            ]
        ],
    ];

    foreach ($subscriberServices as $id => $config) {
        $service = $services->set($id)->tag('kernel.event_subscriber');
        if (isset($config['args'])) {
            $service->args($config['args']);
        }
        if (isset($config['bind'])) {
            foreach ($config['bind'] as $key => $value) {
                $service->bind($key, $value);
            }
        }
    }

    /* ------------------------------
    * Subscribers & Doctrine Listeners
    * ------------------------------*/

    $services->set('Base\Subscriber\IntegritySubscriber')
        ->tag('kernel.event_subscriber')
        ->args([
            new Reference('security.token_storage'),
            new Reference('translator'),
            new Reference('request_stack'),
            new Reference('doctrine'),
            new Reference('advanced_router'),
            new Reference('referrer'),
        ])
        ->bind('$projectDir', '%kernel.project_dir%')
        ->bind('$secret', '%kernel.secret%');


    $services->set('Base\EntityDispatcher\AbstractEventDispatcher')
        ->abstract()
        ->args([
            new Reference('event_dispatcher'),
            new Reference('base.database.entity_hydrator'),
            new Reference('doctrine.orm.entity_manager'),
            new Reference('request_stack'),
        ]);

    foreach ([
        'Base\EntityDispatcher\Event\UserEventDispatcher',
        'Base\EntityDispatcher\Event\ThreadEventDispatcher',
    ] as $dispatcher) {
        $services->set($dispatcher)
            ->parent('Base\EntityDispatcher\AbstractEventDispatcher')
            ->tag('doctrine.event_listener', ['event' => 'prePersist'])
            ->tag('doctrine.event_listener', ['event' => 'postPersist'])
            ->tag('doctrine.event_listener', ['event' => 'preUpdate'])
            ->tag('doctrine.event_listener', ['event' => 'postUpdate'])
            ->tag('doctrine.event_listener', ['event' => 'preRemove'])
            ->tag('doctrine.event_listener', ['event' => 'postRemove']);
    }

    $services->set('Base\EntitySubscriber\ThreadSubscriber')
        ->tag('kernel.event_subscriber')
        ->tag('doctrine.event_listener', ['event' => 'prePersist'])
        ->tag('doctrine.event_listener', ['event' => 'preUpdate'])
        ->tag('doctrine.event_listener', ['event' => 'onFlush'])
        ->args([new Reference('mention_enhancer')]);

    $services->set('Base\EntitySubscriber\UserSubscriber')
        ->tag('kernel.event_subscriber')
        ->args([
            new Reference('base.notifier'),
            new Reference('doctrine.orm.entity_manager'),
            new Reference('security.token_storage'),
            new Reference('advanced_router'),
        ]);


    /* ------------------------------
    * Form Types
    * ------------------------------*/

    $services->set('Base\Form\Type\LayoutSettingListType')
        ->tag('form.type')
        ->args([
            new Reference('setting_bag'),
            new Reference('localizer'),
            new Reference('base.database.metadata_manipulator'),
        ]);

    $services->set('Base\Form\Type\LayoutWidgetListType')
        ->tag('form.type')
        ->args([new Reference('base.widget_provider')]);

    $services->set('Base\Field\Type\PasswordType')
        ->tag('form.type')
        ->args([new Reference('translator'), new Reference('twig')]);

    $services->set('Base\Field\Type\TranslationType')
        ->tag('form.type')
        ->args([
            new Reference('base.database.metadata_manipulator'),
            new Reference('localizer'),
            new Reference('twig'),
        ]);

    $services->set('Base\Field\Type\BooleanType')->tag('form.type')->args([new Reference('twig')]);
    $services->set('Base\Field\Type\ButtonType')->tag('form.type')->args([new Reference('twig')]);

    $services->set('Base\Field\Type\RouteType')
        ->tag('form.type')
        ->args([new Reference('advanced_router'), new Reference('localizer')]);

    $services->set('Base\Field\Type\SelectType')
        ->tag('form.type')
        ->args([
            new Reference('form.factory'),
            new Reference('doctrine.orm.entity_manager'),
            new Reference('translator'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('security.csrf.token_manager'),
            new Reference('localizer'),
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator'),
            new Reference('twig'),
            new Reference('security.authorization_checker'),
            new Reference('obfuscator'),
            new Reference('parameter_bag'),
            new Reference('advanced_router'),
        ]);

    $services->set('Base\Field\Type\ForexType')->parent('Base\Field\Type\SelectType')->tag('form.type');
    $services->set('Base\Field\Type\IconType')->parent('Base\Field\Type\SelectType')->tag('form.type')
        ->args([new Reference('base.service.icon')]);
    $services->set('Base\Field\Type\CountryType')->parent('Base\Field\Type\SelectType')->tag('form.type');

    $services->set('Base\Field\Type\AssociationType')
        ->tag('form.type')
        ->args([
            new Reference('form.factory'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('base.database.entity_hydrator'),
            new Reference('translator'),
        ]);

    $services->set('Base\Field\Type\AssociationFileType')
        ->tag('form.type')
        ->args([
            new Reference('form.factory'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('base.database.entity_hydrator'),
            new Reference('base.service.image'),
        ]);

    $services->set('Base\Field\Type\AttributeType')
        ->tag('form.type')
        ->args([
            new Reference('form.factory'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('twig'),
        ]);

    $services->set('Base\Field\Type\DiscriminatorType')
        ->tag('form.type')
        ->args([
            new Reference('form.factory'),
            new Reference('base.database.metadata_manipulator'),
        ]);

    $services->set('Base\Field\Type\WysiwygType')
        ->tag('form.type')
        ->args([
            new Reference('parameter_bag'),
            new Reference('translator'),
            new Reference('twig'),
        ]);

    $services->set('Base\Field\Type\EditorType')
        ->tag('form.type')
        ->args([
            new Reference('parameter_bag'),
            new Reference('translator'),
            new Reference('twig'),
            new Reference('advanced_router'),
            new Reference('security.csrf.token_manager'),
            new Reference('obfuscator'),
            new Reference('media_enhancer'),
        ]);

    $services->set('Base\Field\Type\MoneyType')
        ->tag('form.type')
        ->args([new Reference('trading_market')]);

    $services->set('Base\Field\Type\NumberType')
        ->tag('form.type')
        ->args([new Reference('twig')]);

    $services->set('Base\Field\Type\StockType')
        ->tag('form.type');

    $services->set('Base\Field\Type\DateTimePickerType')
        ->tag('form.type')
        ->args([
            new Reference('parameter_bag'),
            new Reference('twig'),
            new Reference('localizer'),
        ]);

    $services->set('Base\Field\Type\ColorType')
        ->tag('form.type')
        ->args([
            new Reference('twig'),
            new Reference('parameter_bag'),
        ]);

    $services->set('Base\Field\Type\ColorPickerType')
        ->tag('form.type')
        ->args([new Reference('translator')]);

    $services->set('Base\Field\Type\EmojiPickerType')
        ->tag('form.type');

    $services->set('Base\Field\Type\SlugType')
        ->tag('form.type')
        ->args([
            new Reference('twig'),
            new Reference('base.database.metadata_manipulator'),
        ]);
        
    /* ------------------------------
    * Controllers (UX)
    * ------------------------------*/

    foreach ([
        'Base\Controller\UX\DropzoneController' => [
            'args' => [
                new Reference('translator'),
                new Reference('cache.adapter'),
                new Reference('obfuscator'),
            ],
            'bind' => ['$cacheDir' => '%kernel.cache_dir%']
        ],
    ] as $id => $cfg) {
        $services->set($id)
            ->tag('controller.service_arguments')
            ->tag('container.service_subscriber')
            ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')])
            ->args($cfg['args'])
            ->bind('$cacheDir', '%kernel.cache_dir%');
    }

    $services->set('Base\Controller\UX\EditorController')
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')])
        ->args([
            new Reference('parameter_bag'),
            new Reference('slugger'),
            new Reference('base.service.image'),
            new Reference('flysystem'),
            new Reference('translator'),
            new Reference('request_stack'),
            new Reference('base.paginator'),
            new Reference('obfuscator'),
            new Reference('App\Repository\UserRepository'),
            new Reference('App\Repository\ThreadRepository'),
            new Reference('Base\Repository\Thread\TagRepository'),
            new Reference('profiler', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

    $services->set('Base\Controller\UX\AutocompleteController')
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')])
        ->args([
            new Reference('obfuscator'),
            new Reference('request_stack'),
            new Reference('trading_market'),
            new Reference('translator'),
            new Reference('doctrine.orm.entity_manager'),
            new Reference('base.paginator'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('profiler', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

    $services->set('Base\Controller\UX\AutovalidateController')
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')])
        ->args([
            new Reference('translator'),
            new Reference('doctrine.orm.entity_manager'),
            new Reference('validator'),
        ]);

    $services->set('Base\Controller\Api\ThreadController')
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
        ->call('setContainer', [new Reference('Psr\Container\ContainerInterface')])
        ->args([
            new Reference('doctrine.orm.entity_manager'),
            new Reference('translator'),
            new Reference('App\Repository\ThreadRepository'),
            new Reference('App\Repository\Thread\LikeRepository'),
        ]);

    /* ------------------------------
    * EasyAdmin Field Configurators
    * ------------------------------*/
    
    $services->set('Base\Field\Type\QuadrantType')
        ->tag('form.type')
        ->args([new Reference('twig')]);

    $services->set('Base\Field\Type\CropperType')
        ->tag('form.type')
        ->args([
            new Reference('form.factory'),
            new Reference('doctrine.orm.entity_manager'),
            new Reference('parameter_bag'),
            new Reference('twig'),
        ]);

    $services->set('Base\Field\Type\FileType')
        ->tag('form.type')
        ->args([
            new Reference('parameter_bag'),
            new Reference('translator'),
            new Reference('twig'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('security.csrf.token_manager'),
            new Reference('form.factory'),
            new Reference('advanced_router'),
            new Reference('base.service.image'),
            new Reference('obfuscator'),
            '%kernel.cache_dir%',
        ]);

    $services->set('Base\Field\Type\ImageType')
        ->parent('Base\Field\Type\FileType')
        ->tag('form.type');

    $services->set('Base\Field\Type\AudioType')
        ->parent('Base\Field\Type\FileType')
        ->tag('form.type');

    $services->set('Base\Field\Type\VideoType')
        ->parent('Base\Field\Type\FileType')
        ->tag('form.type');

    $services->set('Base\Field\Type\AvatarType')
        ->parent('Base\Field\Type\ImageType')
        ->tag('form.type');

    $services->set('Base\Field\Type\CollectionType')
        ->tag('form.type')
        ->args([
            new Reference('twig'),
            new Reference('translator'),
            new Reference('security.authorization_checker'),
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator'),
        ]);

    $services->set('Base\Field\Type\ArrayType')
        ->parent('Base\Field\Type\CollectionType')
        ->tag('form.type')
        ->args([new Reference('base.database.metadata_manipulator')]);

    $services->set('Base\Field\Configurator\IdConfigurator')
        ->tag('ea.field_configurator')
        ->args([
            new Reference('security.authorization_checker'),
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator'),
            new Reference('advanced_router'),
        ]);

    $services->set('Base\Field\Configurator\NumberConfigurator')
        ->tag('ea.field_configurator')
        ->args([new Reference('EasyCorp\Bundle\EasyAdminBundle\Intl\IntlFormatter')]);

$services->set(LocaleConfigurator::class)->tag('ea.field_configurator');
    $services->set(TextConfigurator::class)->tag('ea.field_configurator');
    $services->set(WysiwygConfigurator::class)->tag('ea.field_configurator');

    $services->set(StockConfigurator::class)
        ->tag('ea.field_configurator')
        ->args([service(IntlFormatter::class)]);

    $services->set(MoneyConfigurator::class)
        ->tag('ea.field_configurator')
        ->args([
            service(IntlFormatter::class),
            service('property_accessor'),
        ]);

    $services->set(CurrencyConfigurator::class)->tag('ea.field_configurator');
    $services->set(EmailConfigurator::class)->tag('ea.field_configurator');
    $services->set(DateTimePickerConfigurator::class)->tag('ea.field_configurator');
    $services->set(CollectionConfigurator::class)->tag('ea.field_configurator');

    $services->set(ArrayConfigurator::class)
        ->parent(CollectionConfigurator::class)
        ->tag('ea.field_configurator');

    $services->set(SlugConfigurator::class)
        ->tag('ea.field_configurator')
        ->args([service('translator')]);

    $services->set(CropperConfigurator::class)->tag('ea.field_configurator');

    $services->set(FileConfigurator::class)
        ->tag('ea.field_configurator')
        ->args([service('base.database.metadata_manipulator')]);

    $services->set(TranslationConfigurator::class)
        ->tag('ea.field_configurator')
        ->args([
            service('base.database.metadata_manipulator'),
            service('localizer'),
        ]);

    $services->set(BooleanConfigurator::class)
        ->tag('ea.field_configurator')
        ->args([
            service(AdminUrlGenerator::class),
            service('security.csrf.token_manager')->nullOnInvalid(),
        ]);

    $services->set(AssociationConfigurator::class)
        ->tag('ea.field_configurator')
        ->args([
            service('base.database.metadata_manipulator'),
            service(EntityFactory::class),
            service(AdminUrlGenerator::class),
            service('translator'),
        ]);

    $services->set(AssociationFileConfigurator::class)
        ->tag('ea.field_configurator')
        ->args([
            service('base.database.metadata_manipulator'),
            service(EntityFactory::class),
            service(AdminUrlGenerator::class),
            service('translator'),
        ]);

    $services->set(SelectConfigurator::class)
        ->tag('ea.field_configurator')
        ->args([
            service('base.database.metadata_manipulator'),
            service('translator'),
            service(AdminUrlGenerator::class),
        ]);

    $services->set(RoleConfigurator::class)
        ->parent(SelectConfigurator::class)
        ->tag('ea.field_configurator');

    $services->set(StateConfigurator::class)
        ->parent(SelectConfigurator::class)
        ->tag('ea.field_configurator');

    $services->set(CountryConfigurator::class)
        ->parent(SelectConfigurator::class)
        ->tag('ea.field_configurator');

    $services->set(QuadrantConfigurator::class)
        ->parent(SelectConfigurator::class)
        ->tag('ea.field_configurator');

    $services->set(DiscriminatorConfigurator::class)
        ->parent(SelectConfigurator::class)
        ->tag('ea.field_configurator');

    $services->set(AttributeConfigurator::class)
        ->parent(SelectConfigurator::class)
        ->tag('ea.field_configurator');

    $services->set(IconConfigurator::class)
        ->parent(SelectConfigurator::class)
        ->tag('ea.field_configurator')
        ->args([
            service('parameter_bag'),
            service('twig'),
            service('base.service.icon'),
        ]);

    /* ------------------------------
    * Admin + Serializer + DataCollectors
    * ------------------------------*/

    $services->set('Base\Admin\Config\Extension')->public()
        ->args([
            new Reference('twig'),
            new Reference('controller_resolver'),
            new Reference('ea_menu_factory'),
        ]);

    $services->set('Base\Serializer\Encoder\AcoEncoder')->tag('serializer.encoder');
    $services->set('Base\Serializer\Encoder\ExcelEncoder')->tag('serializer.encoder');

    $services->set('Base\Inspector\BundleDataCollector')
        ->tag('data_collector', ['id' => 'base'])
        ->args([
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider'),
            new Reference('doctrine'),
            new Reference('parameter_bag'),
            new Reference('advanced_router'),
            new Reference('base.service'),
        ]);

    $services->set('Base\Inspector\LocalizerDataCollector')
        ->tag('data_collector', ['id' => 'locale'])
        ->args([new Reference('localizer')]);
};