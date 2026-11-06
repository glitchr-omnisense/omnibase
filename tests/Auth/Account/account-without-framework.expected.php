<?php

// What tests/Auth/Account/account-without-framework.php returns: each account page's decision (plan "omnibase agnostic",
// step F5e, slice 5a), its notifications and its flushes, as omnibridge/symfony's controllers answered them.

return [
    'sign-in' => [
        'a visitor' => [
            null,
            [],
            0,
        ],
        'in during a maintenance' => [
            [
                'kind' => 'route',
                'route' => 'security_rescue',
            ],
            [],
            0,
        ],
        'signed in fully' => [
            [
                'kind' => 'back',
            ],
            [],
            0,
        ],
        'remembered only' => [
            null,
            [
                [
                    'login.partial',
                    'info',
                ],
            ],
            0,
        ],
        'links off' => [
            [
                'kind' => 'route',
                'route' => 'security_login',
            ],
            [],
            0,
        ],
        'a valid link' => [
            [
                'kind' => 'back',
                'signIn' => 'account',
                'remember' => true,
            ],
            [],
            0,
        ],
        'a link unknown' => [
            null,
            [],
            0,
        ],
        'by omniauth' => [
            null,
            [],
            0,
        ],
        'a link asked, an account' => [
            [
                'kind' => 'back',
            ],
            [
                [
                    'loginToken.confirmation',
                    'success',
                ],
            ],
            1,
        ],
        'a link asked, no account' => [
            [
                'kind' => 'back',
            ],
            [
                [
                    'loginToken.confirmation',
                    'success',
                ],
            ],
            0,
        ],
        'a link asked, by omniauth' => [
            [
                'kind' => 'back',
            ],
            [
                [
                    'loginToken.confirmation',
                    'success',
                ],
            ],
            0,
        ],
    ],
    'sign-out' => [
        'confirms' => [
            true,
            false,
            false,
            false,
        ],
        'left, put out' => [
            null,
            [
                [
                    'kickout(account)',
                    'warning',
                ],
            ],
            1,
        ],
        'left, kick forgotten' => false,
        'left' => [
            null,
            [
                [
                    'logout.success(account)',
                    'info',
                ],
            ],
            1,
        ],
        'left, nobody' => [
            null,
            [],
            0,
        ],
        'after' => [
            [
                'kind' => 'back',
            ],
            [
                'kind' => 'home',
            ],
        ],
    ],
    'already signed in' => [
        'a visitor' => [
            null,
            [],
            0,
        ],
        'a member' => [
            [
                'kind' => 'route',
                'route' => 'user_profile',
            ],
            [
                [
                    'login.already',
                    'warning',
                ],
            ],
            0,
        ],
    ],
    'verification' => [
        'asked, a visitor' => [
            [
                'kind' => 'denied',
            ],
            [],
            0,
        ],
        'asked, verified' => [
            [
                'kind' => 'route',
                'route' => 'user_profile',
            ],
            [
                [
                    'verifyEmail.already',
                    'info',
                ],
            ],
            1,
        ],
        'asked' => [
            [
                'kind' => 'route',
                'route' => 'user_profile',
            ],
            [
                [
                    'mail.verifyEmail',
                    'success',
                ],
            ],
            1,
        ],
        'the link\'s owner' => true,
        'confirmed, nobody signed in' => [
            [
                'kind' => 'route',
                'route' => 'security_verifyEmail',
            ],
            [
                [
                    'verifyEmail.invalidToken',
                    'danger',
                ],
            ],
            0,
        ],
        'confirmed, no token' => [
            [
                'kind' => 'route',
                'route' => 'user_profile',
            ],
            [
                [
                    'verifyEmail.invalidToken',
                    'danger',
                ],
            ],
            1,
        ],
        'confirmed' => [
            [
                'kind' => 'route',
                'route' => 'user_profile',
            ],
            [
                [
                    'verifyEmail.success',
                    'success',
                ],
                [
                    'mail.adminApproval',
                    'success',
                ],
            ],
            2,
        ],
        'confirmed: verified' => true,
        'confirmed again' => [
            [
                'kind' => 'route',
                'route' => 'user_profile',
            ],
            [
                [
                    'verifyEmail.already',
                    'info',
                ],
            ],
            1,
        ],
        'approval, not verified' => [
            [
                'kind' => 'route',
                'route' => 'user_profile',
            ],
            [
                [
                    'adminApproval.verifyFirst',
                    'warning',
                ],
            ],
            1,
        ],
        'approval, approved' => [
            [
                'kind' => 'route',
                'route' => 'user_profile',
            ],
            [],
            1,
        ],
    ],
    'closing' => [
        'a visitor' => [
            [
                'kind' => 'denied',
            ],
            [],
            0,
        ],
        'closed already' => [
            [
                'kind' => 'index',
            ],
            [
                [
                    'accountGoodbye.already',
                    'warning',
                ],
            ],
            0,
        ],
        'closed' => [
            [
                'kind' => 'index',
            ],
            [],
            1,
        ],
        'welcome back' => [
            [
                'kind' => 'signed-in',
                'signIn' => 'account',
            ],
            [],
            1,
        ],
        'welcome back: enabled' => true,
        'welcome back, open already' => [
            [
                'kind' => 'index',
            ],
            [
                [
                    'accountWelcomeBack.already',
                    'warning',
                ],
            ],
            0,
        ],
        'welcome back, no token' => [
            [
                'kind' => 'index',
            ],
            [
                [
                    'accountWelcomeBack.invalidToken',
                    'danger',
                ],
            ],
            1,
        ],
    ],
    'passwords' => [
        'reset asked, an account' => [
            null,
            [
                [
                    'resetPassword.confirmation',
                    'success',
                ],
            ],
            1,
        ],
        'reset asked, no account' => [
            null,
            [
                [
                    'resetPassword.confirmation',
                    'success',
                ],
            ],
            1,
        ],
        'reset link, unknown' => [
            [
                'kind' => 'index',
            ],
            [
                [
                    'resetPassword.invalidToken',
                    'danger',
                ],
            ],
            0,
        ],
        'reset link, another kind' => [
            [
                'kind' => 'index',
            ],
            [
                [
                    'resetPassword.invalidToken',
                    'danger',
                ],
            ],
            0,
        ],
        'reset link' => [
            'Base\\Entity\\User\\Token',
            [],
            0,
        ],
        'reset' => [
            [
                'kind' => 'index',
                'signIn' => 'account',
                'remember' => true,
            ],
            [
                [
                    'resetPassword.success',
                    'success',
                ],
            ],
            1,
        ],
        'reset: used up' => false,
        'change, a visitor' => [
            [
                'kind' => 'route',
                'route' => 'security_login',
            ],
            [],
            0,
        ],
        'change, no token' => [
            [
                'kind' => 'index',
            ],
            [],
            0,
        ],
        'change' => [
            'Base\\Entity\\User\\Token',
            [],
            0,
        ],
        'changed' => [
            [
                'kind' => 'index',
                'signIn' => 'account',
                'remember' => true,
            ],
            [
                [
                    'changePassword.success',
                    'success',
                ],
            ],
            1,
        ],
    ],
    'waiting' => [
        'maintenance' => [
            'kind' => 'page',
            'page' => [
                'security/maintenance.html.twig',
                [
                    'remainingTime',
                    'percentage',
                    'downtime',
                    'uptime',
                ],
            ],
        ],
        'launch' => [
            'kind' => 'page',
            'page' => [
                'security/launchdate.html.twig',
                [
                    'launchdate',
                    'is_launchedd',
                ],
            ],
        ],
        'pending' => [
            'kind' => 'page',
            'page' => [
                'security/pending.html.twig',
                [],
            ],
        ],
        'waiting for approval' => [
            [
                'kind' => 'page',
                'page' => [
                    'security/pendingForApproval.html.twig',
                    [],
                ],
            ],
            [
                'kind' => 'route',
                'route' => 'app_index',
            ],
        ],
    ],
    'profile' => [
        'shown, by id' => 'other',
        'shown, no such account' => [
            'kind' => 'missing',
            'reason' => 'User not found.',
        ],
        'shown, a visitor' => [
            'kind' => 'route',
            'route' => 'user_search',
        ],
        'shown, one\'s own' => 'account',
        'edited, another\'s' => [
            'kind' => 'denied',
        ],
        'edited, another\'s by an administrator' => 'other',
        'edited, one\'s own' => 'account',
    ],
    'rescue' => [
        'signed in' => [
            'kind' => 'url',
            'url' => 'https://site.test/admin/avatars',
        ],
        'signed in, no referrer' => [
            'kind' => 'url',
            'url' => '/admin',
        ],
        'a visitor' => null,
        'logo' => 'site.png',
        'targets' => [
            '/admin/avatars?page=2',
            '/admin',
            '/admin',
            '/',
        ],
    ],
    'demo' => [
        'off' => [
            [
                'kind' => 'missing',
            ],
            [],
            0,
        ],
        'no token' => [
            [
                'kind' => 'route',
                'route' => 'security_login',
            ],
            [
                [
                    '@notifications.demo.expired',
                    'warning',
                ],
            ],
            0,
        ],
        'undeclared' => [
            [
                'kind' => 'route',
                'route' => 'security_login',
            ],
            [
                [
                    '@notifications.demo.unknown',
                    'warning',
                ],
            ],
            0,
        ],
        'the back office' => [
            [
                'kind' => 'route',
                'route' => 'security_login',
            ],
            [
                [
                    '@notifications.demo.unknown',
                    'warning',
                ],
            ],
            0,
        ],
        'a reader' => [
            [
                'kind' => 'signed-in',
                'signIn' => 'reader',
            ],
            [],
            0,
        ],
    ],
    'locale' => [
        'chosen' => [
            'en-GB',
            'en-GB',
            null,
            null,
        ],
        'remembered, a member' => [
            '@controllers.locale_changeto.action(English)',
            [],
            1,
        ],
        'remembered: the member\'s' => 'en-GB',
        'remembered, impersonating' => [
            '@controllers.locale_changeto.action(English)',
            [],
            0,
        ],
        'referred' => [
            '/japon/articles',
            '/blog',
            null,
            null,
            '/',
        ],
        'back' => [
            [
                [
                    'app_articles.en',
                    [
                        'destination' => 'japon',
                    ],
                ],
                [
                    'app_articles',
                    [
                        'destination' => 'japon',
                    ],
                ],
            ],
            [
                [
                    'blog.en',
                    [],
                ],
                [
                    'blog',
                    [],
                ],
            ],
            [],
            [],
        ],
    ],
];
