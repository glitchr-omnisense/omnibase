<?php

// What tests/Request/Pages/pages-without-framework.php returns under bare --strict (plan "omnibase agnostic", step F5e, slice 5b).

return [
    'second factor' => [
        'a visitor' => [
            'answer' => [
                'kind' => 'denied',
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'a demonstration account' => [
            'answer' => [
                'kind' => 'route',
                'route' => 'user_settings',
            ],
            'sent' => [
                [
                    '@notifications.demo.locked',
                    'warning',
                ],
            ],
            'flushes' => 0,
            'flashes' => [],
        ],
        'switched off' => [
            'answer' => [
                'kind' => 'route',
                'route' => 'user_settings',
            ],
            'sent' => [
                [
                    '@notifications.settings.twoFactorUnavailable',
                    'warning',
                ],
            ],
            'flushes' => 0,
            'flashes' => [],
        ],
        'already on' => [
            'answer' => [
                'kind' => 'route',
                'route' => 'user_settings',
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'the form' => [
            'answer' => null,
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'the pending secret, made' => 'NEW',
        'the pending secret, kept' => 'NEW',
        'carrying' => [
            'NEW',
            null,
        ],
        'waiting' => 'NEW',
        'a wrong code' => [
            'answer' => null,
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'the form says' => 'trans:@notifications.settings.twoFactorInvalidCode',
        'a right code' => [
            'answer' => [
                'kind' => 'page',
                'page' => [
                    'client/user/settings_2fa_backup_codes.html.twig',
                    [
                        'backupCodes',
                    ],
                ],
            ],
            'sent' => [
                [
                    '@notifications.settings.twoFactorEnabled',
                    'success',
                ],
            ],
            'flushes' => 1,
            'flashes' => [],
        ],
        'turned on' => [
            'NEW',
            8,
            null,
        ],
        'nothing waits' => null,
        'its QR' => 'NEW',
        'no QR' => null,
        'off: a demonstration account' => [
            'answer' => [
                'kind' => 'route',
                'route' => 'user_settings',
            ],
            'sent' => [
                [
                    '@notifications.demo.locked',
                    'warning',
                ],
            ],
            'flushes' => 0,
            'flashes' => [],
        ],
        'off: no token' => [
            'answer' => [
                'kind' => 'denied',
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'off: required' => [
            'answer' => [
                'kind' => 'route',
                'route' => 'user_settings',
            ],
            'sent' => [
                [
                    '@notifications.settings.twoFactorMandatory',
                    'warning',
                ],
            ],
            'flushes' => 0,
            'flashes' => [],
        ],
        'off: a wrong password' => [
            'answer' => [
                'kind' => 'route',
                'route' => 'user_settings',
            ],
            'sent' => [
                [
                    '@notifications.settings.wrongPassword',
                    'danger',
                ],
            ],
            'flushes' => 0,
            'flashes' => [],
        ],
        'off' => [
            'answer' => [
                'kind' => 'route',
                'route' => 'user_settings',
            ],
            'sent' => [
                [
                    '@notifications.settings.twoFactorDisabled',
                    'success',
                ],
            ],
            'flushes' => 1,
            'flashes' => [],
        ],
        'turned off' => [
            null,
            0,
        ],
        'backup codes' => [
            true,
            true,
            true,
        ],
    ],
    'devices' => [
        'sessions' => [
            [
                [
                    'user',
                ],
                [
                    'updatedAt' => 'DESC',
                ],
                20,
            ],
        ],
        'rename: no token' => [
            'answer' => [
                'kind' => 'denied',
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'rename: another\'s' => [
            'answer' => [
                'kind' => 'missing',
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'rename: none' => [
            'answer' => [
                'kind' => 'missing',
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'rename' => [
            'answer' => [
                'kind' => 'route',
                'route' => 'user_settings',
            ],
            'sent' => [],
            'flushes' => 1,
            'flashes' => [],
        ],
        'renamed' => 'Laptop',
        'rename to nothing' => [
            'answer' => [
                'kind' => 'route',
                'route' => 'user_settings',
            ],
            'sent' => [],
            'flushes' => 1,
            'flashes' => [],
        ],
        'renamed to nothing' => null,
        'delete: the rename\'s token' => [
            'answer' => [
                'kind' => 'denied',
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'delete' => [
            'answer' => [
                'kind' => 'route',
                'route' => 'user_settings',
            ],
            'sent' => [
                [
                    '@notifications.settings.passkeyDeleted',
                    'success',
                ],
            ],
            'flushes' => 0,
            'flashes' => [],
        ],
        'removed' => [
            1,
        ],
        'sign out: none' => [
            'answer' => [
                'kind' => 'missing',
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'sign out' => [
            'answer' => [
                'kind' => 'route',
                'route' => 'user_settings',
            ],
            'sent' => [],
            'flushes' => 1,
            'flashes' => [],
        ],
    ],
    'members' => [
        'no term' => [],
        'found' => [
            'anne',
            'jean',
        ],
    ],
    'thread search' => [
        'nothing asked' => [
            null,
            [],
        ],
        'the model completed' => [
            'kyoto',
            'tokyo',
            'tokyo',
            null,
        ],
        'the order' => [
            'BareArticle',
            'BareTopic',
        ],
        'searched' => [
            [
                [
                    'title' => 'kyoto',
                    'excerpt' => 'tokyo',
                    'content' => 'tokyo',
                ],
                [
                    'STATE_PUBLISH',
                ],
                null,
            ],
            [
                [
                    'title' => 'kyoto',
                    'excerpt' => 'tokyo',
                    'content' => 'tokyo',
                ],
                [],
                null,
            ],
        ],
    ],
    'thread api' => [
        'no token' => [
            'answer' => [
                'json' => [
                    'code' => 403,
                    'response' => 'Invalid token',
                ],
                'status' => 403,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'publish: not an administrator' => [
            'answer' => [
                'json' => [
                    'code' => 401,
                    'response' => 'Unauthorized',
                ],
                'status' => 200,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'publish: no such thread' => [
            'answer' => null,
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'publish' => [
            'answer' => [
                'json' => [
                    'code' => 200,
                    'response' => 'OK',
                ],
                'status' => 200,
            ],
            'sent' => [],
            'flushes' => 1,
            'flashes' => [],
        ],
        'published' => 'STATE_PUBLISH',
        'hide' => [
            'answer' => [
                'json' => [
                    'code' => 200,
                    'response' => 'OK',
                ],
                'status' => 200,
            ],
            'sent' => [],
            'flushes' => 1,
            'flashes' => [],
        ],
        'hidden' => 'STATE_PUBLISH_SECRET',
        'follow: a visitor' => [
            'answer' => [
                'json' => [
                    'code' => 401,
                    'response' => 'Unauthorized',
                ],
                'status' => 200,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'follow' => [
            'answer' => [
                'json' => [
                    'code' => 200,
                    'response' => 'OK',
                ],
                'status' => 200,
            ],
            'sent' => [],
            'flushes' => 1,
            'flashes' => [],
        ],
        'followers' => 1,
        'unfollow' => [
            'answer' => [
                'json' => [
                    'response' => 'OK',
                ],
                'status' => 200,
            ],
            'sent' => [],
            'flushes' => 1,
            'flashes' => [],
        ],
        'no longer followed' => 0,
        'like: no such thread' => [
            'answer' => null,
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'like: a visitor' => [
            'answer' => [
                'json' => [
                    'response' => 'Unknown user',
                    'likes' => 0,
                ],
                'status' => 401,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'like' => [
            'answer' => [
                'json' => [
                    'response' => 'OK',
                    'likes' => 1,
                ],
                'status' => 200,
            ],
            'sent' => [],
            'flushes' => 1,
            'flashes' => [
                [
                    'info',
                    'trans:@controllers.thread.like',
                ],
            ],
        ],
        'unlike: a visitor' => [
            'answer' => [
                'json' => [
                    'response' => 'Unknown user',
                    'likes' => 1,
                ],
                'status' => 401,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'unlike' => [
            'answer' => [
                'json' => [
                    'response' => 'OK',
                    'likes' => 1,
                ],
                'status' => 201,
            ],
            'sent' => [],
            'flushes' => 1,
            'flashes' => [
                [
                    'info',
                    'trans:@controllers.thread.unlike',
                ],
            ],
        ],
    ],
    'notifications' => [
        'a visitor\'s page' => [
            'answer' => [
                'kind' => 'route',
                'route' => 'security_login',
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'the page, unread' => [
            [
                [
                    100,
                    0,
                    true,
                ],
            ],
            3,
        ],
        'push: a visitor' => [
            'answer' => [
                'json' => [
                    'enabled' => false,
                    'publicKey' => 'VAPID',
                ],
                'status' => 200,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'push: a member' => [
            'answer' => [
                'json' => [
                    'enabled' => true,
                    'publicKey' => 'VAPID',
                ],
                'status' => 200,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'push: off' => [
            'answer' => [
                'json' => [
                    'enabled' => false,
                    'publicKey' => null,
                ],
                'status' => 200,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'subscribe: no token' => [
            'answer' => [
                'json' => [
                    'success' => false,
                ],
                'status' => 403,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'subscribe: off' => [
            'answer' => [
                'json' => [
                    'success' => false,
                    'error' => 'Push is not configured.',
                ],
                'status' => 503,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'subscribe: no endpoint' => [
            'answer' => [
                'json' => [
                    'success' => false,
                    'error' => 'No endpoint.',
                ],
                'status' => 400,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'subscribe' => [
            'answer' => [
                'json' => [
                    'success' => true,
                ],
                'status' => 200,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'unsubscribe: a visitor' => [
            'answer' => [
                'json' => [
                    'success' => false,
                ],
                'status' => 403,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'unsubscribe' => [
            'answer' => [
                'json' => [
                    'success' => true,
                    'removed' => true,
                ],
                'status' => 200,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'a body' => [
            [
                'token' => 't',
            ],
            [
                'form' => 1,
            ],
        ],
        'the service worker' => true,
    ],
    'contact' => [
        [
            'answer' => [
                'template' => 'client/contact/success.html.twig',
                'data' => [],
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        [
            [
                'sendContactEmail',
                'Anne',
            ],
            [
                'sendContactEmailConfirmation',
                'Anne',
            ],
        ],
    ],
    'test e-mail' => [
        'the account signed in' => 'account',
        'the technical recipient\'s account' => 'tech',
        'an address alone' => 'tech',
        'made for the e-mail' => [
            'Base\\Entity\\User',
            'tech@site.test',
            null,
        ],
    ],
    'timezone' => [
        'chosen' => [
            'Europe/Paris',
            'UTC',
            null,
            null,
        ],
        'remembered, a member' => [
            'answer' => null,
            'sent' => [],
            'flushes' => 1,
            'flashes' => [],
        ],
        'the member\'s' => 'Asia/Tokyo',
        'remembered, impersonating' => [
            'answer' => null,
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'back' => [
            '/japon/articles',
            null,
            null,
            null,
        ],
        'cookie' => 'LOCATION/TIMEZONE',
    ],
    'error page' => [
        'a developer' => 'rescue',
        'a developer, previewing' => [
            'exception.html.twig',
        ],
        'production' => [
            'exception.html.twig',
        ],
        'production, the back office' => [
            '@Admin/error.html.twig',
            'exception.html.twig',
        ],
    ],
    'short link' => [
        'followed' => [
            'kind' => 'url',
            'url' => 'https://site.test/a/long/address',
        ],
        'none' => [
            'kind' => 'missing',
            'reason' => 'Page requested doesn\'t exist.',
        ],
    ],
    'attachment' => [
        'downloaded' => [
            true,
            'application/pdf',
        ],
        'none' => null,
        'a text' => 'text/x-php',
        'no file' => 'text/plain',
    ],
    'sitemap' => [
        [
            [
                'setHostname',
                'https://site.test',
            ],
            [
                'registerAttributes',
            ],
        ],
        [
            [
                'Base\\Event\\SitemapEvent',
                'sitemap.build',
                'https://site.test',
            ],
        ],
        'sitemap.xml.twig',
    ],
    'welcome' => [
        true,
        true,
        true,
        404,
    ],
    'autovalidate' => [
        'decoded' => [
            'token' => 'token-validation',
            'value' => 'x',
            'form_type' => 'Form',
        ],
        'nothing hidden' => [],
        'debug, GET' => [
            'answer' => [
                'json' => [
                    'status' => 'Fine',
                ],
                'status' => 200,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'debug, POST' => [
            'answer' => [
                'json' => 'Invalid request',
                'status' => 500,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'production, POST' => [
            'answer' => [
                'json' => [
                    'status' => 'Fine',
                ],
                'status' => 200,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'another token' => [
            'answer' => [
                'json' => 'Invalid request',
                'status' => 500,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
    ],
    'exchange rate' => [
        'a wrong token' => [
            'answer' => [
                'json' => 'Invalid token. Please refresh the page and try again',
                'status' => 500,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'nothing hidden' => [
            'answer' => [
                'json' => 'Invalid token. Please refresh the page and try again',
                'status' => 500,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
        'no rate stored' => [
            'answer' => [
                'json' => 'No rate stored for EUR/JPY',
                'status' => 404,
            ],
            'sent' => [],
            'flushes' => 0,
            'flashes' => [],
        ],
    ],
];
