<?php

/*
 * The core's services of the group "core" (Base\Services::definitions(), plan "omnibase agnostic", step F5e slice 4):
 * each a class (or its parent's), its id, the contracts it answers and its arguments by position or by name, as data -
 * '@id' a service ('@?id' none if absent), '%name%' a parameter; its tags, calls and decoration; under `symfony`, what
 * only Symfony's container gives it (its own services' ids), which replaces the arguments there. Read alike by
 * omnibridge/symfony (in this group's place among its own definitions), omnibridge/laravel and Omnibridge\Container.
 * Written from the definitions the former config/services/core.php registered; docs/20-architecture/services.md.
 */

return [
    [
        'class' => 'Base\Service\Referrer',
        'requires' => ['Base\Routing\AdvancedRouterInterface'],
        'public' => true,
        'arguments' => ['@Omnibridge\Contract\Requests'],
        'contracts' => [
            'referrer' => ['public' => true],
            'Base\Service\ReferrerInterface' => ['public' => true, 'target' => 'referrer'],
        ],
        'symfony' => ['arguments' => ['@Omnibridge\Contract\Requests', '@advanced_router']],
    ],
    ['class' => 'Base\Auth\Account\RescuePage', 'requires' => ['Base\Routing\AdvancedRouterInterface'], 'public' => true, 'autowire' => true],
    ['class' => 'Base\Service\Analytics\UserAgentClassifier', 'public' => true],
    [
        'class' => 'Base\Service\Analytics',
        'public' => true,
        'arguments' => [2 => '@Base\Service\Analytics\UserAgentClassifier'],
        'symfony' => [
            'arguments' => [
                '@Base\Repository\Analytics\PageViewRepository',
                '@Base\Repository\Analytics\VisitRepository',
                '@Base\Service\Analytics\UserAgentClassifier',
                '@Base\Repository\Analytics\PageVisitRepository',
            ],
        ],
    ],
    [
        'class' => 'Base\Service\SettingBag',
        'public' => true,
        'arguments' => [1 => '@Omnibridge\Persistence\UnitOfWork', 3 => '@localizer'],
        'bind' => ['$environment' => '%kernel.environment%'],
        'tags' => ['twig.runtime'],
        'contracts' => [
            'Base\Service\SettingBagInterface' => ['public' => true, 'target' => 'setting_bag'],
            'setting_bag' => ['public' => true],
        ],
        'symfony' => [
            'arguments' => [
                '@parameter_bag',
                '@Omnibridge\Persistence\UnitOfWork',
                '@Base\Repository\Layout\SettingRepository',
                '@localizer',
                '@cache.adapter',
            ],
        ],
    ],
    ['class' => 'Base\Service\SecurityPolicy', 'public' => true, 'autowire' => true],
    [
        'class' => 'Base\EntityDispatcher\Event\ThreadEventDispatcher',
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
    ],
    [
        'class' => 'Base\EntityDispatcher\Event\UserEventDispatcher',
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
    ],
    [
        'class' => 'Base\Service\GroupManager',
        'requires' => ['Base\Repository\User\GroupRepository', 'Base\Repository\User\PermissionRepository'],
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
    ],
    [
        'class' => 'Base\Service\TrashManager',
        'public' => true,
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork', '@Base\Repository\Extension\TrashBalls'],
        'contracts' => ['trash_manager' => ['public' => true]],
    ],
    [
        'class' => 'Base\Service\VersionManager',
        'public' => true,
        'arguments' => [
            '@Omnibridge\Persistence\UnitOfWork',
            '@Base\Repository\Extension\Revisions',
            '%base.extension.max_revisions%',
        ],
        'contracts' => ['version_manager' => ['public' => true]],
    ],
    [
        'class' => 'Base\EntitySubscriber\ThreadSubscriber',
        'requires' => ['Symfony\Component\EventDispatcher\EventSubscriberInterface'],
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
        'arguments' => [
            '$mentionEnhancer' => ['!service_closure' => '@Base\Service\Model\Wysiwyg\MentionEnhancerInterface'],
        ],
        'tags' => [
            ['name' => 'doctrine.event_listener', 'event' => 'prePersist'],
            ['name' => 'doctrine.event_listener', 'event' => 'onFlush'],
        ],
    ],
    [
        'class' => 'Base\EntitySubscriber\UserSubscriber',
        'requires' => ['Symfony\Component\EventDispatcher\EventSubscriberInterface'],
        'public' => true,
        'autowire' => true,
        'autoconfigure' => true,
        'tags' => [['name' => 'doctrine.event_listener', 'event' => 'preFlush']],
    ],
    [
        'id' => 'Base\Notifier\Notifier',
        'parent' => 'Base\Notifier\Abstract\BaseNotifier',
        'public' => true,
        'contracts' => [
            'App\Notifier\Notifier' => ['public' => true],
            'Base\Notifier\NotifierInterface' => ['public' => true, 'target' => 'base.notifier'],
            'base.notifier' => ['public' => true, 'target' => 'App\Notifier\Notifier'],
        ],
    ],
    // Slice 5b of step F5e: the contact page's message and the profiler's test e-mail's recipient.
    ['class' => 'Base\Notifier\ContactMessage', 'public' => true, 'arguments' => ['@Base\Notifier\NotifierInterface']],
    ['class' => 'Base\Notifier\TestRecipient', 'public' => true, 'autowire' => true, 'arguments' => ['$notifier' => '@Base\Notifier\NotifierInterface']],
    [
        'class' => 'Base\Notifier\Abstract\BaseNotifier',
        'public' => true,
        'abstract' => true,
        'arguments' => [
            2 => '@Omnibridge\Persistence\UnitOfWork',
            5 => '@localizer',
            7 => '@Omnibridge\Contract\Views',
            8 => '@setting_bag',
        ],
        'bind' => ['$debug' => '%kernel.debug%'],
        'calls' => [['setUsers', ['@Base\Repository\Users']]],
        'symfony' => [
            'arguments' => [
                '@notifier',
                '@notifier.channel_policy',
                '@Omnibridge\Persistence\UnitOfWork',
                '@parameter_bag',
                '@translator',
                '@localizer',
                '@advanced_router',
                '@Omnibridge\Contract\Views',
                '@setting_bag',
            ],
            'calls' => [['setLogger', ['@??logger']], ['setUsers', ['@Base\Repository\Users']]],
        ],
    ],
    [
        'class' => 'Base\Cache\Abstract\AbstractLocalCache',
        'public' => true,
        'bind' => ['$cacheDir' => '%kernel.cache_dir%'],
    ],
    [
        'class' => 'Base\Service\Model\EntityNouns',
        'public' => true,
        'factory' => ['@Base\Service\Translator', 'getEntityNouns'],
        'contracts' => ['Base\Contract\Nouns' => ['public' => true]],
    ],
    [
        'class' => 'Base\Controller\Action\Notification\Latest',
        'public' => true,
        'arguments' => [
            '@Omnibridge\Contract\Access',
            '@Base\Repository\User\Notifications',
            '@?Base\Notifier\NotificationLinkerInterface',
        ],
        'tags' => ['controller.service_arguments'],
    ],
    [
        'class' => 'Base\Controller\Action\Notification\Read',
        'public' => true,
        'arguments' => ['@Omnibridge\Contract\Access', '@Omnibridge\Contract\Tokens', '@Base\Repository\User\Notifications'],
        'tags' => ['controller.service_arguments'],
    ],
    [
        'class' => 'Base\Controller\Action\Notification\Delete',
        'public' => true,
        'arguments' => ['@Omnibridge\Contract\Access', '@Omnibridge\Contract\Tokens', '@Base\Repository\User\Notifications'],
        'tags' => ['controller.service_arguments'],
    ],
    [
        'class' => 'Base\Controller\Action\Settings\Enrolment',
        'public' => true,
        'arguments' => [
            '@Omnibridge\Contract\Access',
            '@Base\Service\SecurityPolicy',
            '@Omnibridge\Contract\Urls',
            '@?Omniauth\Contract\State',
        ],
        'tags' => ['controller.service_arguments'],
    ],
    [
        'class' => 'Base\Controller\Action\Settings\EnrolmentSkip',
        'public' => true,
        'arguments' => [
            '@Omnibridge\Contract\Access',
            '@Omnibridge\Contract\Tokens',
            '@Base\Service\SecurityPolicy',
            '@Omnibridge\Contract\Urls',
            '@?Omniauth\Contract\State',
        ],
        'tags' => ['controller.service_arguments'],
    ],
    [
        'class' => 'Base\Controller\Action\Settings\EmailCodeToggle',
        'public' => true,
        'arguments' => [
            '@Omnibridge\Contract\Access',
            '@Omnibridge\Contract\Tokens',
            '@Base\Service\SecurityPolicy',
            '@Omnibridge\Contract\Urls',
            '@Base\Repository\Users',
        ],
        'tags' => ['controller.service_arguments'],
    ],
    [
        'class' => 'Base\Controller\Action\Widget\Page',
        'requires' => ['Base\Repository\Layout\Widget\PageRepository'],
        'public' => true,
        'tags' => ['controller.service_arguments'],
        'symfony' => ['arguments' => ['@Base\Repository\Layout\Widget\PageRepository']],
    ],
    [
        'class' => 'Base\Screen\Builder\Accounts',
        'public' => true,
        'arguments' => ['@Base\Service\SecurityPolicy', '@Omnibridge\Contract\Urls', '@Omnibridge\Contract\Tokens'],
    ],
    [
        'id' => 'Base\Service\Localizer',
        'parent' => 'Base\Cache\Abstract\AbstractLocalCache',
        'public' => true,
        'tags' => ['twig.runtime'],
        'contracts' => [
            'localizer' => ['public' => true],
            'Base\Service\LocalizerInterface' => ['public' => true, 'target' => 'localizer'],
        ],
        'symfony' => ['arguments' => ['@parameter_bag', '@translator']],
    ],
    [
        'id' => 'Base\Service\Themizer',
        'parent' => 'Base\Cache\Abstract\AbstractLocalCache',
        'requires' => ['Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface'],
        'public' => true,
        'contracts' => [
            'themizer' => ['public' => true],
            'Base\Service\ThemizerInterface' => ['public' => true, 'target' => 'themizer'],
        ],
        'symfony' => ['arguments' => ['@parameter_bag', '@security.token_storage']],
    ],
    [
        'id' => 'Base\Cache\SimpleCache',
        'parent' => 'Base\Cache\Abstract\AbstractLocalCache',
        'public' => true,
        'contracts' => [
            'simple_cache' => ['public' => true],
            'Base\Cache\SimpleCacheInterface' => ['public' => true, 'target' => 'simple_cache'],
        ],
    ],
    [
        'class' => 'Base\Service\MaintenanceProvider',
        'requires' => ['Base\Routing\AdvancedRouterInterface'],
        'public' => true,
        'arguments' => [
            1 => '@setting_bag',
            2 => '@Omnibridge\Contract\Access',
            4 => '@localizer',
            5 => '@Omnibridge\Contract\Access',
        ],
        'contracts' => [
            'base.service.maintenance' => ['public' => true],
            'Base\Service\MaintenanceProviderInterface' => ['public' => true, 'target' => 'base.service.maintenance'],
        ],
        'symfony' => [
            'arguments' => [
                '@advanced_router',
                '@setting_bag',
                '@Omnibridge\Contract\Access',
                '@parameter_bag',
                '@localizer',
                '@Omnibridge\Contract\Access',
            ],
        ],
    ],
    [
        'class' => 'Base\Service\Launcher',
        'requires' => ['Base\Routing\AdvancedRouterInterface'],
        'public' => true,
        'arguments' => [2 => '@setting_bag', 3 => '@Omnibridge\Contract\Access', 4 => '@Omnibridge\Contract\Access'],
        'contracts' => [
            'base.service.launcher' => ['public' => true],
            'Base\Service\LauncherInterface' => ['public' => true, 'target' => 'base.service.launcher'],
        ],
        'symfony' => [
            'arguments' => [
                '@advanced_router',
                '@parameter_bag',
                '@setting_bag',
                '@Omnibridge\Contract\Access',
                '@Omnibridge\Contract\Access',
            ],
        ],
    ],
    [
        'class' => 'Base\Service\Breadgrinder',
        'requires' => ['Symfony\Component\Routing\RouterInterface'],
        'public' => true,
        'contracts' => [
            'base.breadgrinder' => ['public' => true],
            'Base\Service\BreadgrinderInterface' => ['public' => true, 'target' => 'base.breadgrinder'],
        ],
        'symfony' => ['arguments' => ['@advanced_router', '@translator', '@parameter_bag']],
    ],
    [
        'class' => 'Base\Service\WidgetProvider',
        'public' => true,
        'arguments' => ['@Base\Repository\Layout\Widgets', '@Base\Repository\Layout\Widget\Slots'],
        'tags' => ['twig.runtime'],
        'contracts' => [
            'base.widget_provider' => ['public' => true],
            'Base\Service\WidgetProviderInterface' => ['public' => true, 'target' => 'base.widget_provider'],
        ],
    ],
    [
        'class' => 'Base\Mailer\MailTrap',
        'public' => true,
        'arguments' => ['%kernel.project_dir%/var/mail-trap'],
    ],
    [
        'id' => 'Base\Service\TimeMachine',
        'requires' => ['Backup\Manager\Manager'],
        'public' => true,
        'arguments' => ['@flysystem'],
        'contracts' => [
            'time_machine' => ['public' => true],
            'Base\Service\TimeMachineInterface' => ['public' => true, 'target' => 'time_machine'],
        ],
        'symfony' => ['parent' => 'Backup\Manager\Manager', 'arguments' => ['@flysystem', '@doctrine', '@parameter_bag']],
    ],
];
