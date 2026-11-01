<?php

/*
 * What tests/View/helpers-without-twig.php returns where Twig is not installed
 * (BareTest::testTheViewHelpersWorkWithoutTwig()). The dates and the amounts are those Twig gives for
 * the same input (ViewHelpersTest).
 */

return [
    'twig loaded' => [],
    'formats' => [
        'highlight' => 'Le petit <mark class="markdown-word"><mark class="markdown-pattern">chat</mark></mark> dort',
        'highlight, gate' => '[..] <mark class=\'markdown-gate\'>trois <mark class=\'markdown-word\'><mark class=\'markdown-pattern\'>quatre</mark></mark> cinq</mark> [..]',
        'mailto' => '<a href=\'mailto:ringo@example.org\'>Ringo</a>',
        'mailparse email' => 'ringo@example.org',
        'mailparse name' => 'Ringo Starr',
        'truncate' => 'The Beatles ..',
        'pickup' => [
            'only' => 1,
        ],
        'color name' => 'red',
        'preg split' => [
            'a',
            'b',
            'c',
        ],
        'pad' => [
            1,
            0,
            0,
        ],
        'short class' => 'arrayObject',
        'static call' => '<a href=\'mailto:a@b.c\'>A</a>',
        'greater than' => true,
        'moment' => true,
    ],
    'dates' => [
        'timestamp' => '10 avril 2026, 20:05',
        'moment' => 'vendredi 10 avril',
        'utc' => '18:05',
        'word' => 'présent',
        'none' => 'dd/MM',
        'patterns' => [
            '10',
            '04',
        ],
    ],
    'money' => [
        'euros' => '1 234,50 €',
        'yen, no fraction' => '¥1,500',
    ],
    'pagination' => [
        'sliding control' => [
            'pagination',
            'path',
            'path_parameters',
            'rewind',
            'fastforward',
            'separator',
        ],
        'first' => '<a href=\'/blog?page=1\'>first</a>',
        'previous ones' => [
            '<a href=\'/blog?page=2\'>2</a>',
        ],
        'current' => 'current(3,5)',
        'next ones' => [
            '<a href=\'/blog?page=4\'>4</a>',
        ],
        'last separator' => '...',
        'fast forward' => '<a href=\'/blog?page=5\'>fastforward(5)</a>',
    ],
    'links' => [
        'urlify' => '<a href=\'/blog\' class="nav highlight">Blog</a>',
        'linkify' => '/about',
    ],
    'enums' => [
        'by class' => 'Base\\Enum\\UserRole',
        'colorify' => NULL,
    ],
    'menus' => [
        'available_locales' => [
            'en-GB',
            'fr-FR',
        ],
        'current_locale' => 'fr-FR',
    ],
    'deploy version' => 'dev',
];
