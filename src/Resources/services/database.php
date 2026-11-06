<?php

/*
 * The core's services of the group "database" (Base\Services::definitions(), plan "omnibase agnostic", step F5e slice 4):
 * each a class (or its parent's), its id, the contracts it answers and its arguments by position or by name, as data -
 * '@id' a service ('@?id' none if absent), '%name%' a parameter; its tags, calls and decoration; under `symfony`, what
 * only Symfony's container gives it (its own services' ids), which replaces the arguments there. Read alike by
 * omnibridge/symfony (in this group's place among its own definitions), omnibridge/laravel and Omnibridge\Container.
 * Written from the definitions the former config/services/database.php registered; docs/20-architecture/services.md.
 */

return [
    [
        'class' => 'Base\Attributes\AttributeReader',
        'requires' => [
            'Doctrine\ORM\EntityManager',
            'Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator',
        ],
        'public' => true,
        'arguments' => [4 => '@flysystem', 7 => '@base.database.entity_hydrator'],
        'bind' => [
            '$projectDir' => '%kernel.project_dir%',
            '$environment' => '%kernel.environment%',
            '$cacheDir' => '%kernel.cache_dir%',
        ],
        'contracts' => ['base.attribute_reader' => ['public' => true]],
        'symfony' => [
            'arguments' => [
                '@event_dispatcher',
                '@advanced_router',
                '@doctrine.orm.entity_manager',
                '@parameter_bag',
                '@flysystem',
                '@request_stack',
                '@security.token_storage',
                '@base.database.entity_hydrator',
                '@base.database.metadata_manipulator',
            ],
        ],
    ],
    [
        'class' => 'Base\Database\Entity\EntityExtension',
        'public' => true,
        'contracts' => ['base.entity_extension' => ['public' => true]],
    ],
];
