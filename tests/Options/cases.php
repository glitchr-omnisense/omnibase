<?php

/*
 * Configurations of base: given to Symfony's tree (omnibridge/symfony's BaseConfiguration) and to Omnibridge\Options\Processor
 * (what omnibridge/laravel validates config/base.php with), each a list of configurations merged in their order: tests/Options/
 * OptionsHostsTest. The first ones are valid, the ones after "Refused" are not.
 */
// lesringostars' config/packages/base.yaml (2026-11-06), as Symfony's YAML loader reads it; a typed %env(bool:...)% is
// given as the boolean Symfony's container hands the tree.
$site = array (
  'autoclear' => true,
  'spam' => 
  array (
    'akismet' => '%env(AKISMET_KEY)%',
  ),
  'guard' => 
  array (
    'reputation' => 
    array (
      0 => 'emails',
    ),
  ),
  'uploader' => 
  array (
    'storage' => '%env(MEDIA_UPLOADS_STORAGE)%',
    'formats' => 
    array (
      0 => 
      array (
        'width' => 250,
        'height' => 250,
      ),
      1 => 
      array (
        'width' => 500,
        'height' => 500,
      ),
      2 => 
      array (
        'width' => 1000,
        'height' => 1000,
      ),
      3 => 
      array (
        'width' => 2000,
        'height' => 2000,
      ),
    ),
  ),
  'images' => 
  array (
    'debug' => false,
    'enable_webp' => true,
    'fallback' => true,
    'max_resolution' => 'Base\\Imagine\\Filter\\Basic\\Definition\\UltraHighDefinitionFilter',
    'max_quality' => 0.8,
    'timeout' => 240,
  ),
  'obfuscator' => 
  array (
    'cache_dir' => '%kernel.project_dir%/var/storage/obfuscator/%kernel.environment%',
  ),
  'site' => 
  array (
    'index' => 'app_index',
    'maintenance' => 
    array (
      'redirect_on_deny' => 'security_maintenance',
      'lockpath' => '%kernel.project_dir%/public/maintenance.lock',
    ),
  ),
  'access_restriction' => 
  array (
    'firewalls' => 
    array (
      0 => 'main',
    ),
    'route_exceptions' => 
    array (
      0 => '/^(?:locale_|ux_|user_|security_)/',
    ),
  ),
  'database' => 
  array (
    'use_custom' => true,
  ),
  'parameter_bag' => 
  array (
    'use_hot_bag' => true,
    'use_setting_bag' => true,
  ),
  'router' => 
  array (
    'use_custom' => true,
    'use_fallbacks' => true, // %env(bool:HTTP_REDUCTION)%: Symfony's container gives the processor a boolean
    'host_fallbacks' => 
    array (
      0 => 
      array (
        'machine' => '%env(json:HTTP_MACHINE)%',
        'subdomain' => '%env(json:HTTP_SUBDOMAIN)%',
        'domain' => '%env(json:HTTP_DOMAIN)%',
        'port' => '%env(json:HTTP_PORT)%',
        'base_dir' => '%env(HTTP_BASEDIR)%',
      ),
    ),
  ),
  'notifier' => 
  array (
    'technical_loopback' => false, // %env(bool:MAILER_LOOPBACK)%
    'technical_recipient' => 
    array (
      'email' => 'noreply@example.org',
    ),
    'admin_role' => 'ROLE_EDITOR',
    'test_recipients' => 
    array (
      0 => '@(?:local|localhost|localhost\\.local)$',
      1 => '@example.org$',
    ),
    'options' => 
    array (
      0 => 
      array (
        'channel' => 'email',
        'markAsRead' => false,
      ),
      1 => 
      array (
        'channel' => 'email+',
        'markAsRead' => false,
      ),
      2 => 
      array (
        'channel' => 'browser+',
        'markAsRead' => true,
      ),
      3 => 
      array (
        'channel' => 'discord',
        'markAsRead' => true,
      ),
      4 => 
      array (
        'channel' => 'inapp',
        'markAsRead' => false,
      ),
      5 => 
      array (
        'channel' => 'push',
        'markAsRead' => false,
      ),
    ),
  ),
  'twig' => 
  array (
    'use_custom' => true,
    'use_form2' => true,
    'use_bootstrap' => true,
    'default_path' => '%base.package_dir%/templates',
    'form_themes' => 
    array (
      0 => '@Base/form/form_div_layout.html.twig',
    ),
    'themes' => 
    array (
      'widths' => 
      array (
        'auto' => 
        array (
          'icon' => 'fa-solid fa-fw fa-arrows-left-right',
          'class' => 'theme-width-auto',
        ),
        'narrow' => 
        array (
          'icon' => 'fa-solid fa-fw fa-text-width',
          'class' => 'theme-width-narrow',
        ),
        'wide' => 
        array (
          'icon' => 'fa-solid fa-fw fa-arrows-left-right-to-line',
          'class' => 'theme-width-wide',
        ),
        'full' => 
        array (
          'icon' => 'fa-solid fa-fw fa-arrows-to-circle',
          'class' => 'theme-width-full',
        ),
      ),
      'modes' => 
      array (
        'auto' => 
        array (
          'icon' => 'fa-solid fa-fw fa-circle-half-stroke',
          'prefers' => 'light dark',
        ),
        'dark' => 
        array (
          'icon' => 'fa-solid fa-fw fa-moon',
          'prefers' => 'dark',
        ),
        'light' => 
        array (
          'icon' => 'fa-solid fa-fw fa-sun',
          'prefers' => 'light',
        ),
      ),
      'filters' => 
      array (
        'grayscale' => 
        array (
          'icon' => 'fa-solid fa-fw fa-brain',
        ),
        'sepia' => 
        array (
          'icon' => 'fa-solid fa-fw fa-droplet',
        ),
        'invert' => 
        array (
          'icon' => 'fa-solid fa-fw fa-palette',
        ),
      ),
      'audiences' => 
      array (
        'child' => 
        array (
          'icon' => 'fa-solid fa-fw fa-child',
          'age' => 13,
        ),
        'teen' => 
        array (
          'icon' => 'fa-solid fa-fw fa-user',
          'age' => 18,
        ),
      ),
      'events' => 
      array (
        'halloween' => 
        array (
          'icon' => 'fa-solid fa-fw fa-ghost',
          'begin' => '2025-10-25 00:00:00',
          'end' => '2025-11-02 23:59:59',
        ),
        'olympics' => 
        array (
          'icon' => 'fa-solid fa-fw fa-flag-checkered',
          'begin' => '2025-07-15',
          'end' => '2025-09-15',
        ),
        'summer' => 
        array (
          'icon' => 'fa-solid fa-fw fa-sun',
          'begin' => '2025-06-01',
          'end' => '2025-09-30',
        ),
      ),
    ),
    'editor' => 
    array (
      'storage' => '%env(MEDIA_WYSIWYG_STORAGE)%',
    ),
    'script_attributes' => 
    array (
      'defer' => true,
    ),
    'link_attributes' => 
    array (
      'defer' => true,
    ),
    'breakpoints' => 
    array (
      0 => 
      array (
        'name' => 'mobile',
        'media' => 'all and (max-width: 470px)',
      ),
      1 => 
      array (
        'name' => 'mobile.landscape',
        'media' => 'all and (max-width: 470px) and (orientation: landscape)',
      ),
      2 => 
      array (
        'name' => 'tablet',
        'media' => 'all and (min-width: 471px) and (max-width: 1024px)',
      ),
      3 => 
      array (
        'name' => 'tablet.landscape',
        'media' => 'all and (min-width: 471px) and (max-width: 1024px) and (orientation: landscape)',
      ),
      4 => 
      array (
        'name' => 'laptop',
        'media' => 'all and (min-width: 1025px) and (max-width: 1280px)',
      ),
      5 => 
      array (
        'name' => 'desktop',
        'media' => 'all and (min-width: 1281px)',
      ),
    ),
    'paths' => 
    array (
      0 => 
      array (
        'path' => '%kernel.project_dir%/templates/layout',
      ),
      1 => 
      array (
        'path' => '%kernel.project_dir%/templates/layout',
        'namespace' => 'Layout',
      ),
    ),
  ),
  'attributes' => 
  array (
    'use_custom' => true,
    'paths' => 
    array (
      0 => '%kernel.project_dir%/src/Annotation',
    ),
  ),
  'extension' => 
  array (
    'max_revisions' => 5,
    'empty_trash' => '+7 days',
  ),
  'user' => 
  array (
    'login_with_token' => true,
    'token_default_throttling' => 180,
    'register' => 
    array (
      'autoapprove' => true,
      'notify_admins' => true,
    ),
    'identifier' => 'firstname',
    'notifications' => 
    array (
      'expiry' => '+30 days',
    ),
  ),
);
$twin = [
    'router' => ['use_fallbacks' => true, 'host_fallbacks' => [['locale' => null, 'domain' => ['lesringostars.laravel.test', 'localhost']]]],
    'uploader' => ['storage' => 'local.uploads'],
    'images' => ['debug' => false, 'enable_webp' => true, 'fallback' => true, 'max_resolution' => 'X', 'max_quality' => 0.8, 'timeout' => 240],
    'obfuscator' => ['cache_dir' => '/srv/x/dev'],
    'twig' => ['editor' => ['storage' => 'local.wysiwyg']],
    'notifier' => ['mailer' => true, 'technical_loopback' => false, 'technical_recipient' => ['email' => 'a@b.c', 'phone' => null], 'admin_role' => 'ROLE_EDITOR',
        'test_recipients' => ['@x$', '@example.org$'], 'options' => [['channel' => 'email', 'markAsRead' => false], ['channel' => 'push', 'markAsRead' => true]]],
    'user' => ['login_with_token' => true, 'token_default_throttling' => 180, 'register' => ['autoapprove' => true, 'notify_admins' => true], 'identifier' => 'firstname', 'notifications' => ['expiry' => '+30 days'], 'active_delay' => 60, 'online_delay' => 300],
];
return [
    'nothing' => [],
    'empty' => [[]],
    'null root' => [null],
    'a site' => [$site],
    'twin' => [$twin],
    'merge lists' => [['notifier' => ['test_recipients' => ['a']]], ['notifier' => ['test_recipients' => ['b']]]],
    'no deep merging' => [['security' => ['two_factor' => ['required_roles' => ['ROLE_STAFF']]]], ['security' => ['two_factor' => ['required_roles' => ['ROLE_ADMIN']]]]],
    'no deep merging empty' => [['security' => ['two_factor' => ['required_roles' => ['ROLE_STAFF']]]], ['security' => ['two_factor' => ['required_roles' => []]]]],
    'keyed maps' => [['twig' => ['themes' => ['modes' => [['name' => 'dark', 'class' => 'd'], ['name' => 'x']], 'audiences' => ['kids' => ['age' => 6]], 'events' => [['name' => 'xmas', 'begin' => '12-01']]]]]],
    'keyed merge' => [['twig' => ['themes' => ['audiences' => ['kids' => ['age' => 6]]]]], ['twig' => ['themes' => ['audiences' => ['kids' => ['icon' => 'k'], 'adults' => ['age' => 18]]]]]],
    'null bool' => [['autoclear' => null, 'router' => ['use_custom' => null, 'use_fallbacks' => null]]],
    'null array' => [['database' => null, 'share' => null, 'twig' => ['paths' => null]]],
    'true array' => [['database' => true]],
    'dash keys' => [['site' => ['maintenance' => ['redirect-on-deny' => 'x']], 'local-business' => ['price-range' => '€']]],
    'float as int' => [['local_business' => ['geo' => ['latitude' => 48, 'longitude' => 7.75]]]],
    'enum ok' => [['translator' => ['politeness' => 'polite'], 'guard' => ['unreachable' => 'reject']]],
    'enum null' => [['translator' => ['politeness' => null]]],
    'deprecated set' => [['images' => ['autorotate' => false], 'router' => ['ip_access' => true], 'site' => ['maintenance' => ['exception' => [['code' => 'a', 'value' => 'x']]]]]],
    'default children given' => [['access_restriction' => ['firewalls' => ['api', 'main']], 'attributes' => ['paths' => []]]],
    'exceptions' => [['access_restriction' => ['exceptions' => [['path' => '^/api'], ['host' => 'x', 'env' => 'dev']]]]],
    'variable nodes' => [['opening_hours' => ['week' => [1 => [['09:00', '12:00']]]], 'local_business' => ['extra' => ['a' => ['b' => 1]]]]],
    'string keyed prototype' => [['twig' => ['paths' => ['a' => ['path' => 'p', 'namespace' => 'n']]]]],
    // Refused
    'unknown root' => [['nope' => 1]],
    'unknown guess' => [['autoclea' => 1]],
    'unknown many' => [['user' => ['identifer' => 'x', 'delay' => 3]]],
    'unknown in prototype' => [['notifier' => ['options' => [['channel' => 'email', 'mark' => true]]]]],
    'unknown one available' => [['screens' => ['versions' => 1]]],
    'bool as string' => [['autoclear' => 'yes']],
    'nullable bool as int' => [['router' => ['use_custom' => 1]]],
    'int as string' => [['user' => ['active_delay' => '60']]],
    'int null' => [['obfuscator' => ['encoding' => null]]],
    'float as string' => [['local_business' => ['geo' => ['latitude' => 'north']]]],
    'scalar as array' => [['site' => ['index' => ['a']]]],
    'array as string' => [['database' => 'x']],
    'array as false' => [['database' => false]],
    'list as string' => [['demo' => ['admin_roles' => 'ROLE_ADMIN']]],
    'too small' => [['guard' => ['min_delay' => -1]]],
    'too small ttl' => [['download_links' => ['ttl' => 0]]],
    'enum refused' => [['translator' => ['politeness' => 'rude']]],
    'enum int' => [['guard' => ['unreachable' => 3]]],
    'enum bool' => [['guard' => ['unreachable' => true]]],
    'key missing' => [['twig' => ['themes' => ['modes' => [['class' => 'c']]]]]],
    'duplicate key' => [['twig' => ['themes' => ['modes' => [['name' => 'a'], 'a' => ['class' => 'x']]]]]],
    'refused in second config' => [['autoclear' => true], ['spam' => ['akismet' => ['k']]]],
    'scalar in prototype' => [['demo' => ['exclude' => [['x']]]]],
];
