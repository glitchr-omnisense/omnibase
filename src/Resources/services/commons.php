<?php

/*
 * The core's services of the group "commons" (Base\Services::definitions(), plan "omnibase agnostic", step F5e slice 4):
 * each a class (or its parent's), its id, the contracts it answers and its arguments by position or by name, as data -
 * '@id' a service ('@?id' none if absent), '%name%' a parameter; its tags, calls and decoration; under `symfony`, what
 * only Symfony's container gives it (its own services' ids), which replaces the arguments there. Read alike by
 * omnibridge/symfony (in this group's place among its own definitions), omnibridge/laravel and Omnibridge\Container.
 * Written from the definitions the former config/services/commons.php registered; docs/20-architecture/services.md.
 */

return [
    [
        'class' => 'Base\Service\OpeningHours',
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
        'tags' => [['name' => 'kernel.reset', 'method' => 'reset']],
    ],
    ['class' => 'Base\Service\Calendar\Ics', 'public' => true, 'autowire' => true, 'autoconfigure' => true],
    [
        'class' => 'Base\Service\Calendar\GoogleCalendarLink',
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
    ],
    [
        'class' => 'Base\Service\Invitations',
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
        'arguments' => ['$userClass' => 'App\Entity\User'],
    ],
    [
        'class' => 'Base\Service\DownloadLinks',
        'requires' => [
            'Symfony\Component\HttpFoundation\UriSigner',
            'Symfony\Component\Routing\Generator\UrlGeneratorInterface',
        ],
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
        'symfony' => ['arguments' => ['$signer' => '@uri_signer']],
    ],
    ['class' => 'Base\Service\Qr\QrCode', 'public' => true, 'autowire' => true, 'autoconfigure' => true],
    ['class' => 'Base\Service\Qr\QrSheet', 'public' => true, 'autowire' => true, 'autoconfigure' => true],
    [
        'class' => 'Base\Service\FormGuard',
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
        'symfony' => [
            'arguments' => [
                '$registry' => '@?Omnishield\Registry',
                '$comments' => '@?Base\Repository\Thread\Comments',
                '$logger' => '@?logger',
                '$cache' => '@?cache.app',
            ],
        ],
    ],
    ['class' => 'Base\Service\CommentGuard', 'public' => true, 'autowire' => true, 'autoconfigure' => true],
    [
        'class' => 'Base\Service\Signatures',
        'requires' => ['Omnisign\Registry'],
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
        'symfony' => [
            'arguments' => [
                '$registry' => '@?Omnisign\Registry',
                '$flysystem' => '@flysystem',
                '$dispatcher' => '@?event_dispatcher',
                '$logger' => '@?logger',
            ],
        ],
    ],
    [
        'class' => 'Base\Controller\SignatureController',
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
        'arguments' => ['$signatures' => '@?Base\Service\Signatures'],
        'tags' => ['controller.service_arguments'],
    ],
    [
        'class' => 'Base\Service\Embeds',
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
        'symfony' => ['arguments' => ['$cache' => '@?cache.app', '$logger' => '@?logger']],
    ],
    [
        'class' => 'Base\Translation\OverridingTranslator',
        'requires' => ['Symfony\Bundle\FrameworkBundle\Translation\Translator'],
        'public' => true,
        'autowire' => false,
        'autoconfigure' => false,
        'arguments' => ['@.inner'],
        'tags' => [['name' => 'kernel.reset', 'method' => 'reset']],
        'symfony' => [
            'arguments' => [
                '@.inner',
                ['!service_closure' => '@Omnibridge\Doctrine\Omnibase\Repository\Layout\TextOverrideRepository'],
                '@base.text_override.cache',
            ],
            'decorates' => ['id' => 'translator', 'priority' => 1],
        ],
    ],
    [
        'class' => 'Base\Translation\TextOverrideCacheListener',
        'public' => true,
        'autowire' => false,
        'autoconfigure' => false,
        'arguments' => [['!service_closure' => '@Base\Translation\OverridingTranslator']],
        'tags' => [
            ['name' => 'doctrine.event_listener', 'event' => 'postPersist'],
            ['name' => 'doctrine.event_listener', 'event' => 'postUpdate'],
            ['name' => 'doctrine.event_listener', 'event' => 'postRemove'],
            ['name' => 'doctrine.event_listener', 'event' => 'postFlush'],
        ],
    ],
];
