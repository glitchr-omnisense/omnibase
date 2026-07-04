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
