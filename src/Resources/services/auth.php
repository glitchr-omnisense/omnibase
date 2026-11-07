<?php

/*
 * The core's services of the group "auth" (Base\Services::definitions(), plan "omnibase agnostic", step F5e slice 4):
 * each a class (or its parent's), its id, the contracts it answers and its arguments by position or by name, as data -
 * '@id' a service ('@?id' none if absent), '%name%' a parameter; its tags, calls and decoration; under `symfony`, what
 * only Symfony's container gives it (its own services' ids), which replaces the arguments there. Read alike by
 * omnibridge/symfony (in this group's place among its own definitions), omnibridge/laravel and Omnibridge\Container.
 * Written from the definitions the former config/services/auth.php registered; docs/20-architecture/services.md.
 */

return [
    [
        'class' => 'Base\Auth\UserAccounts',
        'requires' => ['Omniauth\Contract\Accounts'],
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork'],
        'contracts' => ['Omniauth\Contract\Accounts'],
    ],
    [
        'class' => 'Base\Auth\UserPasswords',
        'requires' => ['Omniauth\Contract\Accounts'],
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork'],
        'contracts' => ['Omniauth\Contract\Passwords'],
    ],
    [
        'class' => 'Base\Auth\UserLinkTokens',
        'requires' => ['Omniauth\Contract\Accounts'],
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork'],
        'contracts' => ['Omniauth\Contract\LinkTokens'],
    ],
    [
        'class' => 'Base\Auth\UserEmailCodes',
        'requires' => ['Omniauth\Contract\Accounts'],
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork'],
        'contracts' => ['Omniauth\Contract\EmailCodes'],
    ],
    [
        'class' => 'Base\Auth\UserBackupCodes',
        'requires' => ['Omniauth\Contract\Accounts'],
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork'],
        'contracts' => ['Omniauth\Contract\BackupCodes'],
    ],
    [
        'class' => 'Base\Auth\UserTrustedVersion',
        'requires' => ['Omniauth\Contract\Accounts'],
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork'],
        'contracts' => ['Omniauth\Contract\TrustedVersion'],
    ],
    [
        'class' => 'Base\Auth\UserIdentities',
        'requires' => ['Omniauth\Contract\Accounts'],
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork'],
        'contracts' => ['Omniauth\Contract\Identities'],
    ],
    [
        'class' => 'Base\Auth\UserTotpSecrets',
        'requires' => ['Omniauth\Contract\Accounts'],
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork'],
        'contracts' => ['Omniauth\Contract\TotpSecrets'],
    ],
    [
        'class' => 'Base\Auth\UserPasskeys',
        'requires' => ['Omniauth\Contract\Accounts', 'Omniauth\WebAuthn\Contract\Passkeys'],
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork'],
        'contracts' => ['Omniauth\WebAuthn\Contract\Passkeys'],
    ],
    [
        'class' => 'Base\Auth\NotifierSender',
        'requires' => ['Omniauth\Contract\Accounts'],
        'arguments' => ['@Base\Notifier\Notifier'],
        'contracts' => ['Omniauth\Contract\Sender'],
    ],
    [
        'class' => 'Base\Auth\Policy',
        'requires' => ['Omniauth\Contract\Accounts'],
        'arguments' => ['@Base\Service\SecurityPolicy', '@.inner'],
        'decorates' => ['id' => 'Omniauth\Contract\SecondFactorPolicy', 'on_invalid' => 'ignore'],
        'tags' => [
            [
                'name' => 'kernel.event_listener',
                'event' => 'Omniauth\Event\Revoking',
                'method' => 'onRevoking',
            ],
        ],
    ],
    [
        'class' => 'Base\Auth\PolicyLinking',
        'requires' => ['Omniauth\Contract\Accounts', 'Omniauth\OAuth\NoSecondFactorLinking'],
        'arguments' => ['@Base\Auth\Policy'],
        'symfony' => [
            'arguments' => [
                '@Base\Auth\Policy',
                [
                    '!new' => [
                        'class' => 'Omniauth\OAuth\NoSecondFactorLinking',
                        'arguments' => ['@omniauth.second_factors'],
                    ],
                ],
            ],
        ],
    ],
    [
        'class' => 'Base\Auth\Listener\TrackConnections',
        'requires' => [
            'Omniauth\Contract\Accounts',
            'Symfony\Component\HttpFoundation\RequestStack',
            'Base\Routing\AdvancedRouterInterface',
        ],
        'arguments' => ['@Base\Security\UserTracker', '@Omnibridge\Persistence\UnitOfWork', '@Omniauth\Contract\Accounts'],
        'tags' => [
            [
                'name' => 'kernel.event_listener',
                'event' => 'Omniauth\Event\SignedIn',
                'method' => 'onSignedIn',
                'priority' => 16,
            ],
            [
                'name' => 'kernel.event_listener',
                'event' => 'Omniauth\Event\SignInFailed',
                'method' => 'onSignInFailed',
            ],
        ],
    ],
    [
        'class' => 'Base\Auth\Listener\WarnNewDevice',
        'requires' => [
            'Omniauth\Contract\Accounts',
            'Symfony\Component\HttpFoundation\RequestStack',
            'Base\Routing\AdvancedRouterInterface',
        ],
        'arguments' => [
            0 => '@Base\Service\SecurityPolicy',
            1 => '@Base\Security\UserTracker',
            4 => '@?Omniauth\Contract\State',
        ],
        'tags' => [
            [
                'name' => 'kernel.event_listener',
                'event' => 'Omniauth\Event\SignedIn',
                'method' => 'onSignedIn',
                'priority' => 8,
            ],
        ],
        'symfony' => [
            'arguments' => [
                '@Base\Service\SecurityPolicy',
                '@Base\Security\UserTracker',
                '@Base\Repository\User\Connections',
                '@request_stack',
                '@?Omniauth\Contract\State',
            ],
            'factory' => ['Base\Bridge\Symfony\AuthListeners', 'warnNewDevice'],
        ],
    ],
    [
        'class' => 'Base\Auth\Listener\UpdateAccount',
        'requires' => ['Omniauth\Contract\Accounts'],
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork'],
        'tags' => [
            [
                'name' => 'kernel.event_listener',
                'event' => 'Omniauth\Event\SignedIn',
                'method' => 'onSignedIn',
            ],
        ],
    ],
    [
        'class' => 'Base\Auth\Listener\WelcomeBack',
        'requires' => ['Omniauth\Contract\Accounts'],
        'arguments' => ['@Omnibridge\Persistence\UnitOfWork', '@Base\Notifier\Notifier'],
        'tags' => [
            [
                'name' => 'kernel.event_listener',
                'event' => 'Omniauth\Event\AccountRefused',
                'method' => 'onAccountRefused',
            ],
        ],
    ],
    [
        'class' => 'Base\Auth\Listener\GuardSignIn',
        'requires' => ['Omniauth\Contract\Accounts'],
        'tags' => [
            [
                'name' => 'kernel.event_listener',
                'event' => 'Omniauth\Event\Verifying',
                'method' => 'onVerifying',
            ],
            [
                'name' => 'kernel.event_listener',
                'event' => 'Omniauth\Event\SignInFailed',
                'method' => 'onSignInFailed',
            ],
            [
                'name' => 'kernel.event_listener',
                'event' => 'Omniauth\Event\SignedIn',
                'method' => 'onSignedIn',
            ],
        ],
        'symfony' => [
            'arguments' => ['@Base\Security\SignInGuard', '@request_stack'],
            'factory' => ['Base\Bridge\Symfony\AuthListeners', 'guardSignIn'],
        ],
    ],
];
