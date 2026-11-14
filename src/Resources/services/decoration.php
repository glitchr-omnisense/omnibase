<?php

/*
 * The core's services of the group "decoration" (Base\Services::definitions(), plan "omnibase agnostic", step F5e slice 4):
 * each a class (or its parent's), its id, the contracts it answers and its arguments by position or by name, as data -
 * '@id' a service ('@?id' none if absent), '%name%' a parameter; its tags, calls and decoration; under `symfony`, what
 * only Symfony's container gives it (its own services' ids), which replaces the arguments there. Read alike by
 * omnibridge/symfony (in this group's place among its own definitions), omnibridge/laravel and Omnibridge\Container.
 * Written from the definitions the former config/services/services-decoration.php registered; docs/20-architecture/services.md.
 */

return [
    [
        'class' => 'Base\Twig\AppVariable',
        'requires' => [
            'Twig\Environment',
            'Omnibridge\Symfony\Omnibase\Routing\AdvancedRouterInterface',
            'Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface',
        ],
        'public' => true,
        'arguments' => [
            0 => '@.inner',
            1 => '@twig.random_variable',
            2 => '@twig.site_variable',
            3 => '@twig.email_variable',
            4 => '@twig.admin_variable',
            5 => '@setting_bag',
            7 => '@referrer',
            9 => '@localizer',
            10 => '@themizer',
        ],
        'tags' => ['twig.runtime'],
        'symfony' => [
            'arguments' => [
                '@.inner',
                '@twig.random_variable',
                '@twig.site_variable',
                '@twig.email_variable',
                '@twig.admin_variable',
                '@setting_bag',
                '@parameter_bag',
                '@referrer',
                '@twig',
                '@localizer',
                '@themizer',
            ],
            'decorates' => ['id' => 'twig.app_variable'],
        ],
    ],
    [
        'class' => 'Base\Twig\Loader\FilesystemLoader',
        'requires' => [
            'Twig\Environment',
            'Twig\Loader\FilesystemLoader',
            'Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface',
        ],
        'public' => true,
        'arguments' => [0 => '@.inner', 2 => '%kernel.project_dir%'],
        'contracts' => ['twig.loader.filesystem' => ['public' => true]],
        'symfony' => [
            'arguments' => ['@.inner', '@parameter_bag', '%kernel.project_dir%'],
            'decorates' => ['id' => 'twig.loader.native_filesystem'],
        ],
    ],
    [
        'id' => 'Base\Twig\Environment',
        'requires' => ['Twig\Environment'],
        'arguments' => [1 => '@localizer'],
        'symfony' => [
            'parent' => 'twig',
            'arguments' => ['@request_stack', '@localizer', '@advanced_router', '@parameter_bag'],
            'decorates' => ['id' => 'twig'],
        ],
    ],
    [
        'class' => 'Base\ApiPlatform\Metadata\Resource\Factory\OverriddenResourceNameCollectionFactory',
        'requires' => ['ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface'],
        'public' => true,
        'arguments' => ['@.inner'],
        'symfony' => [
            'decorates' => [
                'id' => 'api_platform.metadata.resource.name_collection_factory',
                'priority' => -5,
                'on_invalid' => 'ignore',
            ],
        ],
    ],
];
