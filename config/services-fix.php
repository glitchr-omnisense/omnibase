<?php


use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults();

    // Backup Manager
    $services->set('Backup\Manager\Manager')
        ->public(true);

    // Notifier fix
    $services->alias('Symfony\Component\Notifier\Channel\ChannelPolicyInterface', 'notifier.channel_policy');

    // Twig fix
    $services->set('Symfony\Bridge\Twig\Extension\AssetExtension')
        ->tag('twig.runtime')
        ->args([new Reference('assets.packages')]);

    // Doctrine fix
    $services->alias('Doctrine\Bundle\DoctrineBundle\ConnectionFactory', 'doctrine.dbal.connection_factory');

    $services->set('Doctrine\Persistence\Mapping\ClassMetadataFactory')
        ->factory([new Reference('doctrine.orm.default_entity_manager'), 'getMetadataFactory']);

    // EasyAdmin fixes
    $services->set('EasyCorp\Bundle\EasyAdminBundle\Inspector\DataCollector');

    $services->set('EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory')
        ->public(true)
        ->args([
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider'),
            tagged_iterator('ea.filter_configurator'),
        ]);

    $services->set('EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory')
        ->public(true)
        ->args([
            new Reference('security.authorization_checker'),
            new Reference('doctrine'),
            new Reference('event_dispatcher'),
        ]);

    $services->set('EasyCorp\Bundle\EasyAdminBundle\Orm\EntityRepository')
        ->public(true)
        ->args([
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider'),
            new Reference('doctrine'),
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory'),
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Factory\FormFactory'),
            new Reference('event_dispatcher'),
        ]);

    $services->set('EasyCorp\Bundle\EasyAdminBundle\Factory\PaginatorFactory')
        ->public(true)
        ->args([
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider'),
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Orm\EntityPaginator'),
        ]);

    $services->set('EasyCorp\Bundle\EasyAdminBundle\Factory\FormFactory')
        ->public(true)
        ->args([
            new Reference('form.factory'),
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator'),
        ]);

    $services->set('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator')
        ->public(true)
        ->args([
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider'),
            new Reference('Symfony\Component\Routing\Generator\UrlGeneratorInterface'),
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Registry\DashboardControllerRegistry'),
            new Reference('EasyCorp\Bundle\EasyAdminBundle\Router\AdminRouteGenerator'),
            new Reference('cache.easyadmin'),
            new Reference('doctrine.orm.entity_manager'),
        ]);

    $services->set('EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider')
        ->public(true)
        ->args([new Reference('request_stack')]);
};