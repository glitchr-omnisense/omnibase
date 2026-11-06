<?php

/*
 * The core's services of the group "view" (Base\Services::definitions(), plan "omnibase agnostic", step F5e slice 4):
 * each a class (or its parent's), its id, the contracts it answers and its arguments by position or by name, as data -
 * '@id' a service ('@?id' none if absent), '%name%' a parameter; its tags, calls and decoration; under `symfony`, what
 * only Symfony's container gives it (its own services' ids), which replaces the arguments there. Read alike by
 * omnibridge/symfony (in this group's place among its own definitions), omnibridge/laravel and Omnibridge\Container.
 * Written from the definitions the former config/services/view.php registered; docs/20-architecture/services.md.
 */

return [
    [
        'class' => 'Base\View\Commons',
        'public' => true,
        'arguments' => [
            0 => '@Base\Service\Embeds',
            1 => '@Base\Service\Qr\QrCode',
            2 => '@Base\Service\OpeningHours',
            4 => '@?Base\Service\MediaService',
            5 => '%kernel.project_dir%/public',
        ],
        'symfony' => [
            'arguments' => [
                '@Base\Service\Embeds',
                '@Base\Service\Qr\QrCode',
                '@Base\Service\OpeningHours',
                ['!closure' => ['@assets.packages', 'getUrl']],
                '@?Base\Service\MediaService',
                '%kernel.project_dir%/public',
            ],
        ],
    ],
    [
        'class' => 'Base\View\Wysiwyg',
        'public' => true,
        'arguments' => ['@wysiwyg_enhancer', '@editor_enhancer'],
    ],
];
