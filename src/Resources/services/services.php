<?php

/*
 * The core's services of the group "services" (Base\Services::definitions(), plan "omnibase agnostic", step F5e slice 4):
 * each a class (or its parent's), its id, the contracts it answers and its arguments by position or by name, as data -
 * '@id' a service ('@?id' none if absent), '%name%' a parameter; its tags, calls and decoration; under `symfony`, what
 * only Symfony's container gives it (its own services' ids), which replaces the arguments there. Read alike by
 * omnibridge/symfony (in this group's place among its own definitions), omnibridge/laravel and Omnibridge\Container.
 * Written from the definitions the former config/services/services-list.php registered; docs/20-architecture/services.md.
 */

return [
    [
        'class' => 'Base\Service\Model\Obfuscator\Compression\NullCompression',
        'public' => true,
        'autowire' => false,
        'tags' => ['obfuscator.compression'],
    ],
    [
        'class' => 'Base\Service\Model\Obfuscator\Compression\ZlibCompression',
        'public' => true,
        'autowire' => false,
        'tags' => ['obfuscator.compression'],
    ],
    [
        'class' => 'Base\Service\Model\Obfuscator\Compression\GzipCompression',
        'public' => true,
        'autowire' => false,
        'tags' => ['obfuscator.compression'],
    ],
    [
        'class' => 'Base\Service\Model\Obfuscator\Compression\DeflateCompression',
        'public' => true,
        'autowire' => false,
        'tags' => ['obfuscator.compression'],
    ],
    [
        'class' => 'Base\Service\Model\Obfuscator\Compression\HashidsCompression',
        'public' => true,
        'autowire' => false,
        'arguments' => ['$secret' => '%kernel.secret%'],
        'tags' => ['obfuscator.compression'],
    ],
    [
        'class' => 'Base\Service\Obfuscator',
        'public' => true,
        'autowire' => false,
        'arguments' => [
            '$cacheDir' => '%base.obfuscator.cache_dir%',
            '$compressions' => [
                '@Base\Service\Model\Obfuscator\Compression\NullCompression',
                '@Base\Service\Model\Obfuscator\Compression\ZlibCompression',
                '@Base\Service\Model\Obfuscator\Compression\GzipCompression',
                '@Base\Service\Model\Obfuscator\Compression\DeflateCompression',
                '@Base\Service\Model\Obfuscator\Compression\HashidsCompression',
            ],
        ],
        'bind' => ['$cacheDir' => '%base.obfuscator.cache_dir%'],
        'tags' => ['twig.runtime'],
        'contracts' => [
            'obfuscator' => ['public' => true],
            'Base\Service\ObfuscatorInterface' => ['public' => true, 'target' => 'obfuscator'],
        ],
        'symfony' => ['arguments' => ['@parameter_bag']],
    ],
    [
        'class' => 'Base\Service\MediaService',
        'public' => true,
        'autowire' => false,
        'arguments' => ['$imagineBitmap' => '@imagine.bitmap', '$imagineSvg' => '@imagine.svg'],
        'tags' => ['twig.runtime'],
        'contracts' => [
            'base.service.image' => ['public' => true],
            'Base\Service\MediaServiceInterface' => ['public' => true, 'target' => 'base.service.image'],
        ],
        'symfony' => [
            'arguments' => [
                '@?Omnibridge\Contract\Views',
                '@advanced_router',
                '@obfuscator',
                '@flysystem',
                '@parameter_bag',
                '@imagine.bitmap',
                '@imagine.svg',
                '@??profiler',
                '@??logger',
            ],
        ],
    ],
    ['class' => 'Base\Service\Links', 'public' => true, 'autowire' => true],
    [
        'class' => 'Base\Service\Paginator',
        'public' => true,
        'autowire' => false,
        'contracts' => [
            'base.paginator' => ['public' => true],
            'Base\Service\PaginatorInterface' => ['public' => true, 'target' => 'base.paginator'],
        ],
        'symfony' => ['arguments' => ['@advanced_router', '@parameter_bag']],
    ],
    [
        'class' => 'Base\Service\Translator',
        'public' => true,
        'autowire' => false,
        'arguments' => [
            '$translator' => '@Symfony\Component\Translation\Translator',
            '$politeness' => '%base.translator.politeness%',
        ],
        'tags' => ['twig.runtime'],
        'contracts' => ['base.translator' => ['public' => true], 'Base\Service\TranslatorInterface' => ['public' => true]],
        'symfony' => [
            'arguments' => ['@.inner', '@Omnibridge\Contract\Environment', '@parameter_bag', '%base.translator.politeness%'],
            'decorates' => ['id' => 'translator'],
        ],
    ],
    [
        'class' => 'Base\Screen\Builder\Stories',
        'public' => true,
        'autowire' => false,
        'symfony' => ['arguments' => ['@Base\Contract\Texts', '@Base\Contract\Pictures']],
    ],
    [
        'class' => 'Base\Notifier\Mail\EmailComposer',
        'public' => true,
        'autowire' => true,
        'arguments' => ['$from' => '%base.notifier.technical_recipient.email%'],
    ],
    ['class' => 'Base\Form\Definition\FormProcessor', 'public' => true, 'autowire' => true],
    ['class' => 'Base\Field\Search\RemoteSearch', 'public' => true, 'autowire' => true],
    [
        'class' => 'Base\Field\Upload\Dropzone',
        'public' => true,
        'autowire' => true,
        'arguments' => ['$cacheDir' => '%kernel.cache_dir%'],
    ],
    [
        'class' => 'Base\Field\Editor\EditorJs',
        'public' => true,
        'autowire' => true,
        'arguments' => ['$storage' => '%base.twig.editor.storage%'],
    ],
    ['class' => 'Base\Auth\Registration', 'public' => true, 'autowire' => true],
    [
        'class' => 'Base\Database\Entity\EntityHydrator',
        'public' => true,
        'autowire' => true,
        'contracts' => [
            'Base\Database\Entity\EntityHydratorInterface' => ['public' => true, 'target' => 'base.database.entity_hydrator'],
            'base.database.entity_hydrator' => ['public' => true],
        ],
    ],
    [
        'class' => 'Base\Service\BaseService',
        'public' => true,
        'autowire' => true,
        'contracts' => ['base.service' => ['public' => true]],
    ],
];
