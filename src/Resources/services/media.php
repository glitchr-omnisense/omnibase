<?php

/*
 * The core's services of the group "media" (Base\Services::definitions(), plan "omnibase agnostic", step F5e slice 4):
 * each a class (or its parent's), its id, the contracts it answers and its arguments by position or by name, as data -
 * '@id' a service ('@?id' none if absent), '%name%' a parameter; its tags, calls and decoration; under `symfony`, what
 * only Symfony's container gives it (its own services' ids), which replaces the arguments there. Read alike by
 * omnibridge/symfony (in this group's place among its own definitions), omnibridge/laravel and Omnibridge\Container.
 * Written from the definitions the former config/services/media.php registered; docs/20-architecture/services.md.
 */

return [
    [
        'class' => 'Base\Service\SpamChecker',
        'public' => true,
        'arguments' => [
            0 => '@Omnibridge\Contract\Requests',
            1 => '@setting_bag',
            5 => '%kernel.debug%',
            6 => '@Base\Service\FormGuard',
        ],
        'contracts' => [
            'spam_checker' => ['public' => true],
            'Base\Service\SpamCheckerInterface' => ['public' => true, 'target' => 'spam_checker'],
        ],
        'symfony' => [
            'arguments' => [
                '@Omnibridge\Contract\Requests',
                '@setting_bag',
                '@parameter_bag',
                '@translator',
                '@monolog.http_client',
                '%kernel.debug%',
                '@Base\Service\FormGuard',
                '@?advanced_router',
                '@?logger',
            ],
        ],
    ],
    [
        'class' => 'Base\Service\Sitemapper',
        'requires' => [
            'Base\Routing\AdvancedRouterInterface',
            'Doctrine\ORM\EntityManager',
            'Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator',
        ],
        'public' => true,
        'arguments' => [0 => '@Omnibridge\Contract\Views', 1 => '@base.attribute_reader', 3 => '@localizer'],
        'contracts' => [
            'sitemap' => ['public' => true],
            'Base\Service\SitemapperInterface' => ['public' => true, 'target' => 'sitemap'],
        ],
        'symfony' => [
            'arguments' => [
                '@Omnibridge\Contract\Views',
                '@base.attribute_reader',
                '@advanced_router',
                '@localizer',
                '@?logger',
            ],
        ],
    ],
    [
        'class' => 'Base\Service\Model\Wysiwyg\SemanticEnhancer',
        'public' => true,
        'tags' => ['twig.runtime'],
        'contracts' => [
            'semantic_enhancer' => ['public' => true],
            'Base\Service\Model\Wysiwyg\SemanticEnhancerInterface' => ['public' => true, 'target' => 'semantic_enhancer'],
        ],
        'symfony' => ['arguments' => ['@Base\Repository\Layout\SemanticRepository']],
    ],
    [
        'class' => 'Base\Service\Model\Wysiwyg\MentionEnhancer',
        'public' => true,
        'arguments' => [2 => '@Base\Database\Repository\Repositories', 3 => '@obfuscator'],
        'tags' => ['twig.runtime'],
        'contracts' => [
            'mention_enhancer' => ['public' => true],
            'Base\Service\Model\Wysiwyg\MentionEnhancerInterface' => ['public' => true, 'target' => 'mention_enhancer'],
        ],
        'symfony' => [
            'arguments' => [
                '@App\Repository\Thread\MentionRepository',
                '@App\Repository\UserRepository',
                '@Base\Database\Repository\Repositories',
                '@obfuscator',
            ],
        ],
    ],
    ['class' => 'Base\Service\Model\Wysiwyg\LinkEnhancer', 'public' => true, 'tags' => ['twig.runtime']],
    [
        'class' => 'Base\Service\Model\Wysiwyg\MediaEnhancer',
        'public' => true,
        'arguments' => ['@base.service.image', '@flysystem'],
        'tags' => ['twig.runtime'],
        'contracts' => [
            'media_enhancer' => ['public' => true],
            'Base\Service\Model\Wysiwyg\MediaEnhancerInterface' => ['public' => true, 'target' => 'media_enhancer'],
        ],
        'symfony' => ['arguments' => ['@base.service.image', '@flysystem', '@??logger']],
    ],
    [
        'class' => 'Base\Service\Model\Wysiwyg\HeadingEnhancer',
        'public' => true,
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork'],
        'tags' => ['twig.runtime'],
        'contracts' => [
            'heading_enhancer' => ['public' => true],
            'Base\Service\Model\Wysiwyg\HeadingEnhancerInterface' => ['public' => true, 'target' => 'heading_enhancer'],
        ],
        'symfony' => ['arguments' => ['@Omnibridge\Persistence\UnitOfWork', '@slugger']],
    ],
    [
        'class' => 'Base\Service\WysiwygEnhancer',
        'public' => true,
        'arguments' => [
            '@Omnibridge\Contract\Views',
            '@heading_enhancer',
            '@semantic_enhancer',
            '@mention_enhancer',
            '@Base\Service\Model\Wysiwyg\LinkEnhancer',
            '@media_enhancer',
        ],
        'tags' => ['twig.runtime'],
        'contracts' => [
            'wysiwyg_enhancer' => ['public' => true],
            'Base\Service\WysiwygEnhancerInterface' => ['public' => true, 'target' => 'wysiwyg_enhancer'],
        ],
    ],
    [
        'id' => 'Base\Service\EditorEnhancer',
        'parent' => 'Base\Service\WysiwygEnhancer',
        'public' => true,
        'tags' => ['twig.runtime'],
        'contracts' => [
            'editor_enhancer' => ['public' => true],
            'Base\Service\EditorEnhancerInterface' => ['public' => true, 'target' => 'editor_enhancer'],
        ],
    ],
    ['class' => 'Base\Service\Model\Obfuscator\AbstractCompression', 'public' => true, 'abstract' => true],
    [
        'class' => 'Base\Service\FileService',
        'public' => true,
        'arguments' => [0 => '@?Omnibridge\Contract\Views', 2 => '@obfuscator', 3 => '@flysystem'],
        'tags' => ['twig.runtime'],
        'contracts' => [
            'base.service.file' => ['public' => true],
            'Base\Service\FileServiceInterface' => ['public' => true, 'target' => 'base.service.file'],
        ],
        'symfony' => [
            'arguments' => [
                '@?Omnibridge\Contract\Views',
                '@advanced_router',
                '@obfuscator',
                '@flysystem',
                '@parameter_bag',
            ],
        ],
    ],
    [
        'class' => 'Base\Service\Flysystem',
        'public' => true,
        'contracts' => [
            'flysystem' => ['public' => true],
            'Base\Service\FlysystemInterface' => ['public' => true, 'target' => 'flysystem'],
            'Base\Service\Storages' => ['public' => true, 'target' => 'flysystem'],
        ],
        'symfony' => ['arguments' => ['@Omnidrive\Drives']],
    ],
    [
        'class' => 'Base\Imagine\Svg\Imagine',
        'id' => 'imagine.adapter.svg',
        'public' => true,
        'contracts' => ['imagine.svg' => ['public' => true]],
        'symfony' => ['calls' => [['setMetadataReader', ['@imagine.meta_data.reader']]]],
    ],
    [
        'class' => 'Base\Service\Sharing',
        'public' => true,
        'tags' => ['twig.runtime'],
        'contracts' => ['base.service.sharing' => ['public' => true]],
    ],
    [
        'class' => 'Base\Service\Model\Sharing\AbstractSharingAdapter',
        'public' => true,
        'arguments' => ['@Omnibridge\Contract\Views'],
    ],
    [
        'id' => 'Base\Service\Model\Sharing\Adapter\FacebookAdapter',
        'parent' => 'Base\Service\Model\Sharing\AbstractSharingAdapter',
        'tags' => ['base.service.sharing'],
    ],
    [
        'id' => 'Base\Service\Model\Sharing\Adapter\LinkedInAdapter',
        'parent' => 'Base\Service\Model\Sharing\AbstractSharingAdapter',
        'tags' => ['base.service.sharing'],
    ],
    [
        'id' => 'Base\Service\Model\Sharing\Adapter\GooglePlusAdapter',
        'parent' => 'Base\Service\Model\Sharing\AbstractSharingAdapter',
        'tags' => ['base.service.sharing'],
    ],
    [
        'id' => 'Base\Service\Model\Sharing\Adapter\TumblrAdapter',
        'parent' => 'Base\Service\Model\Sharing\AbstractSharingAdapter',
        'tags' => ['base.service.sharing'],
    ],
    [
        'id' => 'Base\Service\Model\Sharing\Adapter\TwitterAdapter',
        'parent' => 'Base\Service\Model\Sharing\AbstractSharingAdapter',
        'tags' => ['base.service.sharing'],
    ],
    [
        'id' => 'Base\Service\Model\Sharing\Adapter\PinterestAdapter',
        'parent' => 'Base\Service\Model\Sharing\AbstractSharingAdapter',
        'tags' => ['base.service.sharing'],
    ],
    [
        'class' => 'Base\Service\IconProvider',
        'requires' => [
            'Base\Routing\AdvancedRouterInterface',
            'Doctrine\ORM\EntityManager',
            'Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator',
        ],
        'public' => true,
        'arguments' => ['@base.attribute_reader', '@base.service.image', '@localizer'],
        'bind' => ['$cacheDir' => '%kernel.cache_dir%'],
        'tags' => ['twig.runtime'],
        'contracts' => ['base.service.icon' => ['public' => true]],
        'symfony' => ['arguments' => ['@base.attribute_reader', '@base.service.image', '@localizer', '@advanced_router']],
    ],
    [
        'class' => 'Base\Service\Model\IconProvider\Adapter\FontAwesomeAdapter',
        'requires' => ['Symfony\Component\Cache\Adapter\PhpArrayAdapter'],
        'public' => true,
        'bind' => [
            '$metadata' => '%kernel.project_dir%/public/bundles/base/metadata/icons.yml',
            '$cacheDir' => '%kernel.cache_dir%',
        ],
        'tags' => ['base.service.icon'],
    ],
    [
        'class' => 'Base\Service\Model\IconProvider\Adapter\BootstrapTwitterAdapter',
        'requires' => ['Symfony\Component\Cache\Adapter\PhpArrayAdapter'],
        'public' => true,
        'bind' => [
            '$metadata' => '%kernel.project_dir%/public/bundles/base/metadata/bootstrap-icons.json',
            '$cacheDir' => '%kernel.cache_dir%',
        ],
        'tags' => ['base.service.icon'],
    ],
    [
        'class' => 'Base\Service\Trading',
        'requires' => ['Symfony\Component\HttpClient\Psr18Client'],
        'public' => true,
        'bind' => ['$cacheDir' => '%kernel.cache_dir%', '$live' => '%env(bool:BASE_TRADING_LIVE)%'],
        'contracts' => [
            'trading_market' => ['public' => true],
            'Base\Service\TradingInterface' => ['public' => true, 'target' => 'trading_market'],
        ],
        'symfony' => ['arguments' => ['@http_client']],
    ],
    [
        'class' => 'Base\Service\Model\Currency\AbstractCurrencyApi',
        'public' => true,
        'arguments' => ['@setting_bag'],
    ],
    [
        'id' => 'Base\Service\Model\Currency\Api\Fixer',
        'parent' => 'Base\Service\Model\Currency\AbstractCurrencyApi',
        'tags' => ['currency.api'],
    ],
    [
        'id' => 'Base\Service\Model\Currency\Api\AbstractApi',
        'parent' => 'Base\Service\Model\Currency\AbstractCurrencyApi',
        'tags' => ['currency.api'],
    ],
    [
        'id' => 'Base\Service\Model\Currency\Api\ExchangeRatesApi',
        'parent' => 'Base\Service\Model\Currency\AbstractCurrencyApi',
        'tags' => ['currency.api'],
    ],
    [
        'id' => 'Base\Service\Model\Currency\Api\CurrencyLayer',
        'parent' => 'Base\Service\Model\Currency\AbstractCurrencyApi',
        'tags' => ['currency.api'],
    ],
];
