<?php

// What tests/Request/rules-without-framework.php returns: omnibase's request rules decided without a framework (plan "omnibase agnostic", step F5e, slice 2).

return [
  'accounts' => [
    'anonymous' => [
      'kind' => 'continue',
    ],
    'an active account' => [
      'kind' => 'continue',
    ],
    'impersonated' => [
      'kind' => 'continue',
      'notices' => [
        0 => [
          0 => 'impersonator',
          1 => [
            0 => 'account',
            1 => '_impersonate',
          ],
          2 => 'warning',
        ],
      ],
    ],
    'kicked' => [
      'kind' => 'redirect',
      'route' => 'security_logoutRequest',
      'parameters' => [
        '_csrf_token' => 'token-logout',
      ],
      'status' => 302,
      'notices' => [
        0 => [
          0 => 'kickout',
          1 => [
            0 => 'account',
          ],
          2 => 'warning',
        ],
      ],
      'final' => true,
      'remember' => 'https://site.test/blog/a',
    ],
    'idle: poked and written' => [
      0 => [
        'kind' => 'continue',
      ],
      1 => true,
      2 => 1,
    ],
    'idle, a sub-request: not poked' => [
      0 => [
        'kind' => 'continue',
      ],
      1 => false,
      2 => 0,
    ],
    'not verified' => [
      'kind' => 'continue',
      'notices' => [
        0 => [
          0 => 'verifyEmail.pending',
          1 => [
            0 => '/security_verifyEmail',
          ],
          2 => 'warning',
        ],
      ],
    ],
    'not verified, a tester' => [
      'kind' => 'continue',
    ],
    'not verified, the account\'s pages' => [
      'kind' => 'continue',
    ],
    'not approved, an administrator' => [
      0 => [
        'kind' => 'continue',
      ],
      1 => true,
      2 => 1,
    ],
    'not approved, autoapprove' => [
      0 => [
        'kind' => 'continue',
      ],
      1 => true,
      2 => 1,
    ],
    'not approved, neither' => [
      0 => [
        'kind' => 'continue',
      ],
      1 => false,
      2 => 0,
    ],
  ],
  'presence' => [
    'on the answer' => [
      0 => true,
      1 => false,
      2 => false,
      3 => 1,
    ],
  ],
  'locale' => [
    'address' => 'de-DE',
    'member before LANG' => 'fr-FR',
    'impersonating: LANG' => 'en-GB',
    'LANG' => 'en-GB',
    'a language not spoken' => 'fr-FR',
    'the browser' => 'en-GB',
    'no word from the browser' => [
      0 => NULL,
      1 => NULL,
    ],
    'remembered' => [
      0 => [
        'kind' => 'continue',
        'cookies' => [
          0 => [
            0 => 'LANG',
            1 => 'fr-FR',
          ],
        ],
      ],
      1 => [
        'kind' => 'continue',
      ],
      2 => [
        'kind' => 'continue',
      ],
    ],
    'time zone' => [
      0 => 'UTC',
      1 => 'Europe/Paris',
      2 => [
        0 => 'UTC',
        1 => [
          'kind' => 'continue',
          'notices' => [
            0 => [
              0 => '@localizer.timezone.invalid',
              1 => [
                0 => 'Mars/Olympus',
              ],
              2 => 'info',
            ],
          ],
        ],
      ],
    ],
  ],
  'integrity' => [
    'intact' => [
      'kind' => 'continue',
    ],
    'broken' => [
      'kind' => 'redirect',
      'route' => 'security_rescue',
      'status' => 302,
      'notices' => [
        0 => [
          0 => 'integrity',
          1 => [
            0 => 'account',
          ],
          2 => 'danger',
        ],
      ],
      'cookies' => [
        0 => [
          0 => 'REMEMBERME',
          1 => NULL,
        ],
      ],
      'final' => true,
      'signOut' => true,
    ],
    'broken, the rescue page' => [
      'kind' => 'continue',
    ],
    'no secret' => true,
    'checksum' => [
      0 => 'b866b3c858dab954bbc216f88c28e92c',
      1 => '',
    ],
    'database' => [
      0 => true,
      1 => true,
      2 => false,
      3 => false,
    ],
  ],
  'analytics' => [
    'applies' => [
      0 => true,
      1 => false,
      2 => false,
      3 => false,
    ],
    'users' => [
      0 => [
        'kept' => 1,
        'label' => '@messages.user_analytics.label(1)',
        'default' => [
          'active' => [
            'label' => '@messages.user_analytics.active_users(1)',
            'icon' => 'fa-solid  fa-user',
          ],
          'online' => [
            'label' => '@messages.user_analytics.online_users(2)',
            'icon' => 'fa-solid  fa-user-clock',
          ],
        ],
      ],
      1 => [
        'online' => 2,
        'active' => 1,
      ],
    ],
    'google' => [
      'label' => 'x',
      'google' => [
        'users' => [
          'label' => '@google_users(3)',
          'icon' => 'fa-solid  fa-user',
        ],
        'sessions' => [
          'label' => '@google_analytics.sessions(2)',
          'icon' => 'fa-solid  fa-stopwatch',
        ],
      ],
    ],
  ],
  'redirection' => [
    'found' => [
      'kind' => 'redirect',
      'url' => '/app/new?utm_source=x',
      'status' => 301,
      'final' => true,
    ],
    'posted' => [
      'kind' => 'continue',
    ],
    'onto itself' => [
      'kind' => 'continue',
    ],
    'elsewhere' => [
      'kind' => 'redirect',
      'url' => 'https://elsewhere.test/',
      'status' => 302,
      'final' => true,
    ],
    'none' => [
      'kind' => 'continue',
    ],
    'no table' => [
      'kind' => 'continue',
    ],
    'hits' => 2,
  ],
  'maintenance' => [
    'over, elsewhere' => [
      'kind' => 'continue',
    ],
    'over, its page' => [
      'kind' => 'redirect',
      'route' => 'app_index',
      'status' => 302,
      'final' => true,
    ],
    'an editor' => [
      'kind' => 'continue',
      'notices' => [
        0 => [
          0 => 'maintenance.banner',
          1 => [
          ],
          2 => 'warning',
        ],
      ],
    ],
    'its page' => [
      'kind' => 'continue',
      'final' => true,
      'profiler' => false,
    ],
    'granted' => [
      'kind' => 'continue',
    ],
    'denied' => [
      'kind' => 'redirect',
      'route' => 'app_closed',
      'status' => 302,
      'final' => true,
      'signOut' => true,
      'profiler' => false,
    ],
  ],
  'launch' => [
    'not asked' => [
      'kind' => 'continue',
    ],
    'no date' => [
      'kind' => 'continue',
    ],
    'the back office' => [
      'kind' => 'continue',
    ],
    'launched' => [
      'kind' => 'continue',
    ],
    'an editor' => [
      'kind' => 'continue',
      'notices' => [
        0 => [
          0 => 'launcher.banner',
          1 => [
            0 => '01 janvier 2030',
            1 => '10:00',
          ],
          2 => 'warning',
        ],
      ],
    ],
    'granted' => [
      'kind' => 'continue',
    ],
    'denied' => [
      'kind' => 'redirect',
      'route' => 'security_launch',
      'status' => 302,
      'final' => true,
      'signOut' => true,
      'profiler' => false,
    ],
  ],
  'access control' => [
    'a page for nobody in particular' => [
      'kind' => 'continue',
    ],
    'public' => [
      'kind' => 'continue',
    ],
    'a visitor on a member\'s page' => [
      'kind' => 'redirect',
      'route' => 'security_login',
      'status' => 302,
      'final' => true,
      'remember' => 'https://site.test/profile/nobody/edit',
    ],
    'a member' => [
      'kind' => 'continue',
    ],
    'a member in the back office' => [
      'kind' => 'deny',
      'status' => 403,
      'final' => true,
    ],
    'the entry point' => [
      0 => [
        'kind' => 'redirect',
        'route' => 'security_login',
        'status' => 302,
        'final' => true,
      ],
      1 => [
        'kind' => 'deny',
        'status' => 403,
        'final' => true,
      ],
    ],
  ],
  'decision' => [
    'kind' => 'redirect',
    'route' => 'b',
    'status' => 302,
    'notices' => [
      0 => [
        0 => 'a',
        1 => [
        ],
        2 => 'info',
      ],
      1 => [
        0 => 'c',
        1 => [
        ],
        2 => 'warning',
      ],
    ],
    'cookies' => [
      0 => [
        0 => 'X',
        1 => '1',
      ],
    ],
    'final' => true,
  ],
];
