<?php

/*
 * The core's services of the group "redirections" (Base\Services::definitions(), plan "omnibase agnostic", step F5e slice 4):
 * each a class (or its parent's), its id, the contracts it answers and its arguments by position or by name, as data -
 * '@id' a service ('@?id' none if absent), '%name%' a parameter; its tags, calls and decoration; under `symfony`, what
 * only Symfony's container gives it (its own services' ids), which replaces the arguments there. Read alike by
 * omnibridge/symfony (in this group's place among its own definitions), omnibridge/laravel and Omnibridge\Container.
 * Written from the definitions the former config/services/redirections.php registered; docs/20-architecture/services.md.
 */

return [
    [
        'class' => 'Base\Service\Redirections',
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
        'symfony' => ['arguments' => ['$logger' => '@?logger']],
    ],
];
