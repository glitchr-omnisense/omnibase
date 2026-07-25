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

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->public(false);

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

    // Former EagerSubscriber: eager construction is obsolete now that
    // AttributeReader's constructor is metadata-free (heavy precompute moved
    // to AttributeCacheWarmer) — only the cache-valid marker remains.
    $services->set('Base\Subscriber\ValidCacheSubscriber')
        ->tag('kernel.event_subscriber');

    $services->set('Base\Subscriber\FlashBagSubscriber')
        ->tag('kernel.event_subscriber');

    // Database Subscribers
    $services->set('Base\DatabaseSubscriber\EnumSubscriber'); // compiler pass

    $services->set('Base\Database\Middleware\PlatformMiddleware')
        ->tag('doctrine.middleware');

    $services->set('Base\DatabaseSubscriber\AttributeSubscriber')
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
            new Reference('base.attribute_reader'),
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
            new Reference('base.attribute_reader'),
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
        ->bind('$cacheDir', '%base.obfuscator.cache_dir%');


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
                new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
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

    $services->set('Base\Service\Sharing')->public()->tag('twig.runtime');

    $services->set('Base\Service\Model\Sharing\AbstractSharingAdapter')
        ->args([new Reference('twig')]);

    foreach ([
        'FacebookAdapter',
        'LinkedInAdapter',
        'GooglePlusAdapter',
        'TumblrAdapter',
        'TwitterAdapter',
        'PinterestAdapter',
    ] as $adapter) {
        $services->set("Base\Service\Model\Sharing\Adapter\\$adapter")
            ->parent('Base\Service\Model\Sharing\AbstractSharingAdapter')
            ->tag('base.service.sharing');
    }


    /* ------------------------------
    * Icon System
    * ------------------------------*/

    $services->set('Base\Service\IconProvider')
        ->public()
        ->tag('twig.runtime')
        ->args([
            new Reference('base.attribute_reader'),
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
};
