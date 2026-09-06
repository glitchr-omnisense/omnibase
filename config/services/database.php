<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;


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

    // Attribute reader
    $services->set('Base\Attributes\AttributeReader')->public()
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

    $services->set('Base\Security\Voter\PermissionVoter')
        ->tag('security.voter');

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
