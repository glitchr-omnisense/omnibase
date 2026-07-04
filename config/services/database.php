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
    $services->set('Base\Attributes\AnnotationReader')->public()
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
};
