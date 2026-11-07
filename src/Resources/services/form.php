<?php

/*
 * The core's services of the group "form" (Base\Services::definitions(), plan "omnibase agnostic", step F5e slice 4):
 * each a class (or its parent's), its id, the contracts it answers and its arguments by position or by name, as data -
 * '@id' a service ('@?id' none if absent), '%name%' a parameter; its tags, calls and decoration; under `symfony`, what
 * only Symfony's container gives it (its own services' ids), which replaces the arguments there. Read alike by
 * omnibridge/symfony (in this group's place among its own definitions), omnibridge/laravel and Omnibridge\Container.
 * Written from the definitions the former config/services/form.php registered; docs/20-architecture/services.md.
 */

return [
    [
        'class' => 'Base\Form\Definition\UniqueLookup',
        'public' => true,
        'arguments' => ['@Base\Database\Repository\Repositories'],
    ],
    [
        'class' => 'Base\Validator\ConstraintValidator',
        'requires' => ['Symfony\Component\Validator\ConstraintValidator'],
        'public' => true,
        'tags' => ['validator.constraint_validator'],
        'symfony' => ['arguments' => ['@translator']],
    ],
    [
        'id' => 'Base\Validator\Constraints\AlphanumericValidator',
        'parent' => 'Base\Validator\ConstraintValidator',
        'requires' => ['Symfony\Component\Validator\ConstraintValidator'],
        'tags' => ['validator.constraint_validator'],
    ],
    [
        'id' => 'Base\Validator\Constraints\AlphanumericPlusValidator',
        'parent' => 'Base\Validator\ConstraintValidator',
        'requires' => ['Symfony\Component\Validator\ConstraintValidator'],
        'tags' => ['validator.constraint_validator'],
    ],
    [
        'id' => 'Base\Validator\Constraints\FileValidator',
        'parent' => 'Base\Validator\ConstraintValidator',
        'requires' => ['Symfony\Component\Validator\ConstraintValidator'],
        'tags' => ['validator.constraint_validator'],
    ],
    [
        'id' => 'Base\Validator\Constraints\HexcodeValidator',
        'parent' => 'Base\Validator\ConstraintValidator',
        'requires' => ['Symfony\Component\Validator\ConstraintValidator'],
        'tags' => ['validator.constraint_validator'],
    ],
    [
        'id' => 'Base\Validator\Constraints\NotBlankValidator',
        'parent' => 'Base\Validator\ConstraintValidator',
        'requires' => ['Symfony\Component\Validator\ConstraintValidator'],
        'tags' => ['validator.constraint_validator'],
    ],
    [
        'id' => 'Base\Validator\ConstraintEntityValidator',
        'parent' => 'Base\Validator\ConstraintValidator',
        'requires' => ['Symfony\Component\Validator\ConstraintValidator'],
        'tags' => ['validator.constraint_validator'],
    ],
    [
        'id' => 'Base\Validator\Constraints\UniqueEntityValidator',
        'parent' => 'Base\Validator\ConstraintEntityValidator',
        'requires' => ['Symfony\Component\Validator\ConstraintValidator'],
        'tags' => ['validator.constraint_validator'],
        'calls' => [['setUnitOfWork', ['@?Omnibridge\Persistence\UnitOfWork']]],
    ],
    [
        'id' => 'Base\Validator\Constraints\StringCaseEntityValidator',
        'parent' => 'Base\Validator\ConstraintEntityValidator',
        'requires' => ['Symfony\Component\Validator\ConstraintValidator'],
        'tags' => ['validator.constraint_validator'],
    ],
    [
        'class' => 'Base\Validator\Constraints\UniqueValidator',
        'requires' => ['Symfony\Component\Validator\ConstraintValidator'],
        'public' => true,
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork'],
        'tags' => ['validator.constraint_validator'],
    ],
    [
        'class' => 'Base\Notifier\Channel\BrowserPlusChannel',
        'public' => true,
        'arguments' => ['@Omnibridge\Contract\Requests'],
        'tags' => [['name' => 'notifier.channel', 'channel' => 'browser+']],
    ],
    [
        'id' => 'Base\Notifier\Channel\EmailPlusChannel',
        'tags' => [['name' => 'notifier.channel', 'channel' => 'email+']],
        'symfony' => ['parent' => 'notifier.channel.email'],
    ],
    [
        'class' => 'Base\Notifier\Channel\InAppChannel',
        'public' => true,
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork', '@Base\Repository\Users'],
        'tags' => [['name' => 'notifier.channel', 'channel' => 'inapp']],
    ],
    [
        'class' => 'Base\Service\Push\WebPushService',
        'public' => true,
        'arguments' => [
            0 => '@Omnibridge\Persistence\UnitOfWork',
            3 => '%env(default::VAPID_PUBLIC_KEY)%',
            4 => '%env(default::VAPID_PRIVATE_KEY)%',
            5 => '%env(default::VAPID_SUBJECT)%',
        ],
        'contracts' => ['base.push' => ['public' => true]],
        'symfony' => [
            'arguments' => [
                '@Omnibridge\Persistence\UnitOfWork',
                '@Base\Repository\User\PushSubscriptions',
                '@?logger',
                '%env(default::VAPID_PUBLIC_KEY)%',
                '%env(default::VAPID_PRIVATE_KEY)%',
                '%env(default::VAPID_SUBJECT)%',
            ],
        ],
    ],
    // Slice 5b of step F5e: the notification center's page and the push subscriptions.
    ['class' => 'Base\Notifier\NotificationCenter', 'public' => true, 'autowire' => true],
    [
        'class' => 'Base\Notifier\NullNotificationLinker',
        'public' => true,
        'contracts' => ['Base\Notifier\NotificationLinkerInterface' => ['public' => true]],
    ],
    [
        'class' => 'Base\Notifier\Channel\PushChannel',
        'public' => true,
        'arguments' => ['@Base\Service\Push\WebPushService', '@Base\Notifier\Channel\InAppChannel'],
        'tags' => [['name' => 'notifier.channel', 'channel' => 'push']],
    ],
    [
        'class' => 'Base\Security\UserTracker',
        'requires' => ['Symfony\Component\HttpFoundation\RequestStack', 'Base\Routing\AdvancedRouterInterface'],
        'public' => true,
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork'],
        'symfony' => [
            'arguments' => [
                '@Omnibridge\Persistence\UnitOfWork',
                '@request_stack',
                '@advanced_router',
                '@Base\Repository\User\Connections',
            ],
        ],
    ],
    [
        'class' => 'Base\EntitySubscriber\ConnectionSubscriber',
        'requires' => [
            'Symfony\Component\EventDispatcher\EventSubscriberInterface',
            'Symfony\Component\HttpFoundation\RequestStack',
            'Base\Routing\AdvancedRouterInterface',
        ],
        'public' => true,
        'arguments' => ['@Base\Security\UserTracker', '@Omnibridge\Persistence\UnitOfWork'],
        'tags' => ['kernel.event_subscriber'],
    ],
];
