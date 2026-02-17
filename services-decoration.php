<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->public(false);

    // ParameterBag services
    $services->alias('Base\Service\ParameterBagInterface', 'Base\Service\ParameterBag');

    $services->set('Base\Service\ParameterBag')
        ->parent('parameter_bag')
        ->decorate('parameter_bag')
        ->public(true);

    $services->set('Base\Service\HotParameterBag')
        ->parent('parameter_bag')
        ->decorate('parameter_bag')
        ->public(true);

    // Translator services
    $services->alias('Base\Service\TranslatorInterface', 'Base\Service\Translator');

    $services->set('Base\Service\Translator')
        ->decorate('translator')
        ->public(true)
        ->tag('twig.runtime')
        ->args([
            new Reference('.inner'),
            new Reference('kernel'),
            new Reference('parameter_bag'),
        ]);

    // Twig AppVariable
    $services->set('Base\Twig\AppVariable')
        ->decorate('twig.app_variable')
        ->public(true)
        ->tag('twig.runtime')
        ->args([
            new Reference('.inner'),
            new Reference('twig.random_variable'),
            new Reference('twig.site_variable'),
            new Reference('twig.email_variable'),
            new Reference('twig.admin_variable'),
            new Reference('setting_bag'),
            new Reference('parameter_bag'),
            new Reference('referrer'),
            new Reference('twig'),
            new Reference('localizer'),
            new Reference('themizer'),
        ]);

    // Twig Loader
    $services->set('Base\Twig\Loader\FilesystemLoader')
        ->decorate('twig.loader.native_filesystem')
        ->args([
            new Reference('.inner'),
            new Reference('parameter_bag'),
            '%kernel.project_dir%',
        ]);

    // Form services
    $services->alias('Base\Form\FormFactoryInterface', 'Base\Form\FormFactory');

    $services->set('Base\Form\FormFactory')
        ->parent('form.factory')
        ->decorate('form.factory')
        ->args([
            new Reference('validator'),
            new Reference('base.database.metadata_manipulator'),
            new Reference('base.database.entity_hydrator'),
        ]);

    // Inspector services
    $services->set('Base\Inspector\HtmlErrorRenderer')
        ->parent('error_handler.error_renderer.html')
        ->decorate('error_handler.error_renderer.html');

    $services->set('Base\Inspector\FileLinkFormatter')
        ->parent('debug.file_link_formatter')
        ->decorate('debug.file_link_formatter')
        ->public(false)
        ->tag('kernel.file_link_formatter');

    // Twig Environment
    $services->set('Base\Twig\Environment')
        ->parent('twig')
        ->decorate('twig')
        ->args([
            new Reference('request_stack'),
            new Reference('localizer'),
            new Reference('advanced_router'),
            new Reference('parameter_bag'),
        ]);

    // Admin services
    $services->set('Base\Admin\EventListener\AdminRouterSubscriber')
        ->parent('EasyCorp\Bundle\EasyAdminBundle\EventListener\AdminRouterSubscriber')
        ->decorate('EasyCorp\Bundle\EasyAdminBundle\EventListener\AdminRouterSubscriber')
        ->tag('kernel.event_subscriber')
        ->args([new Reference('doctrine.orm.entity_manager')]);

    $services->set('Base\Admin\Router\AdminRouteGenerator')
        ->parent('EasyCorp\Bundle\EasyAdminBundle\Router\AdminRouteGenerator')
        ->decorate('EasyCorp\Bundle\EasyAdminBundle\Router\AdminRouteGenerator');

    $services->set('Base\Admin\Router\AdminUrlGenerator')
        ->parent('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator')
        ->decorate('EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator');

    $services->set('Base\Admin\Provider\AdminContextProvider')
        ->parent('EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider')
        ->decorate('EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider');

    $services->set('Base\Admin\Factory\AdminContextFactory')
        ->parent('EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory')
        ->decorate('EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory')
        ->args([new Reference('ea_extension')]);

    $services->set('Base\Admin\Factory\MenuFactory')
        ->parent('EasyCorp\Bundle\EasyAdminBundle\Factory\MenuFactory')
        ->decorate('EasyCorp\Bundle\EasyAdminBundle\Factory\MenuFactory')
        ->args([new Reference('advanced_router')]);

    $services->set('Base\Admin\Field\Configurator\CommonPreConfigurator')
        ->parent('EasyCorp\Bundle\EasyAdminBundle\Field\Configurator\CommonPreConfigurator')
        ->decorate('EasyCorp\Bundle\EasyAdminBundle\Field\Configurator\CommonPreConfigurator')
        ->args([new Reference('translator')]);

    // Console commands
    $services->set('Base\Console\Command\CacheClearCommand')
        ->parent('Base\Console\Command')
        ->decorate('console.command.cache_clear')
        ->tag('console.command')
        ->args([
            new Reference('.inner'),
            new Reference('command.sessions.clear'),
            new Reference('flysystem'),
            new Reference('base.notifier'),
            new Reference('advanced_router'),
        ])
        ->bind('$projectDir', '%kernel.project_dir%')
        ->bind('$cacheDir', '%kernel.cache_dir%');
};