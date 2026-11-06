<?php

/*
 * The core's services of the group "demo" (Base\Services::definitions(), plan "omnibase agnostic", step F5e slice 4):
 * each a class (or its parent's), its id, the contracts it answers and its arguments by position or by name, as data -
 * '@id' a service ('@?id' none if absent), '%name%' a parameter; its tags, calls and decoration; under `symfony`, what
 * only Symfony's container gives it (its own services' ids), which replaces the arguments there. Read alike by
 * omnibridge/symfony (in this group's place among its own definitions), omnibridge/laravel and Omnibridge\Container.
 * Written from the definitions the former config/services/demo.php registered; docs/20-architecture/services.md.
 */

return [
    ['class' => 'Base\Demo\DemoMode', 'public' => true, 'autowire' => true, 'autoconfigure' => true],
    ['class' => 'Base\Demo\DemoSignIn', 'public' => true, 'autowire' => true],
    [
        'class' => 'Base\Demo\DemoAccountRegistry',
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
        'symfony' => ['arguments' => ['$roleHierarchy' => '@?security.role_hierarchy']],
    ],
    [
        'class' => 'Base\Demo\DemoAccountFactory',
        'requires' => ['Doctrine\Persistence\ManagerRegistry'],
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
        'symfony' => ['arguments' => ['$doctrine' => '@doctrine']],
    ],
    [
        'class' => 'Base\Demo\DemoGuard',
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
        'symfony' => ['arguments' => ['$doctrine' => '@?doctrine']],
    ],
    [
        'class' => 'Base\Auth\DemoPasswords',
        'requires' => ['Omniauth\Contract\Passwords'],
        'environment' => 'demo',
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
        'arguments' => [
            '@.inner',
            '@Base\Demo\DemoAccountRegistry',
            '%base.demo.superadmin_password%',
            '%base.demo.admin_password%',
        ],
        'decorates' => ['id' => 'Omniauth\Contract\Passwords', 'on_invalid' => 'ignore'],
    ],
];
