<?php

// What tests/Auth/Policy/policies-without-framework.php returns under docker/bin/bare --strict (step F5e, slice 1).

return [
    'access' => [
        'ADMIN_ACCESS' => true,
        'USER_ACCESS' => false,
        'ANONYMOUS_ACCESS' => false,
    ],
    'exception' => [
        'https://site.test/presse/x' => true,
        'https://site.test/prod/x' => false,
        'https://site.test/blog' => false,
        'api, outside the restricted firewall' => true,
        'no firewall' => true,
    ],
    'maintenance' => [
        false,
        true,
    ],
    'launch' => [
        false,
    ],
    'admin' => [
        false,
        true,
        true,
        false,
    ],
    'complaint' => [
        true,
        false,
        false,
        false,
    ],
    'permission' => [
        true,
        false,
        false,
        false,
        false,
    ],
    'restriction' => [
        'open site' => [
            'kind' => 'open',
            'profiler' => true,
            'notice' => null,
            'routes' => [],
        ],
        'visitor sent away' => [
            'kind' => 'redirect',
            'profiler' => false,
            'notice' => null,
            'routes' => [
                'security_pendingForApproval',
            ],
        ],
        'visitor on the sign-in' => [
            'kind' => 'open',
            'profiler' => false,
            'notice' => null,
            'routes' => [],
        ],
        'visitor in the back office' => [
            'kind' => 'not_found',
            'profiler' => false,
            'notice' => null,
            'routes' => [],
        ],
        'visitor on an exception' => [
            'kind' => 'open',
            'profiler' => false,
            'notice' => null,
            'routes' => [],
        ],
        'visitor on the page the denied go to' => [
            'kind' => 'open',
            'profiler' => false,
            'notice' => null,
            'routes' => [],
        ],
        'granted user' => [
            'kind' => 'open',
            'profiler' => true,
            'notice' => null,
            'routes' => [],
        ],
        'granted super administrator' => [
            'kind' => 'open',
            'profiler' => true,
            'notice' => [
                'access_restricted.user_restriction.message',
                'warning',
            ],
            'routes' => [],
        ],
        'granted user on an exception' => [
            'kind' => 'open',
            'profiler' => true,
            'notice' => [
                'access_restricted.user_restriction.exception',
                'info',
            ],
            'routes' => [],
        ],
        'granted user where the denied go' => [
            'kind' => 'open',
            'profiler' => true,
            'notice' => [
                'access_restricted.user_restriction.on_deny',
                'info',
            ],
            'routes' => [],
        ],
    ],
    'welcome' => [
        'social' => 'login.social',
        'unverified' => null,
        'alien' => 'login.success.alien',
        'this morning' => 'login.success.morning.back',
        'yesterday afternoon' => 'login.success.afternoon.first',
        'two days ago, late' => 'login.success.day.first',
        'today at noon' => 'login.success.day.back',
    ],
];
