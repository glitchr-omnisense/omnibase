<?php

/*
 * The base.* parameters a host gives where its configuration says nothing (omnibridge/symfony's BaseConfiguration's
 * defaults, flattened as its extension sets them; plan "omnibase agnostic", step F5e slice 4): what
 * graph-without-framework.php's plain host is given. Generated; Base\\Options (the options' schema as data, slice 4b)
 * gives them in its place.
 */

return array (
  'base.autoclear' => true,
  'base.database' => 
  array (
    'fallback_warning' => true,
    'use_custom' => NULL,
    'excluded_fields' => 
    array (
      0 => 'id',
      1 => 'locale',
      2 => 'translatable',
    ),
  ),
  'base.database.fallback_warning' => true,
  'base.database.use_custom' => NULL,
  'base.database.excluded_fields' => 
  array (
    0 => 'id',
    1 => 'locale',
    2 => 'translatable',
  ),
  'base.database.excluded_fields.0' => 'id',
  'base.database.excluded_fields.1' => 'locale',
  'base.database.excluded_fields.2' => 'translatable',
  'base.share' => 
  array (
  ),
  'base.user' => 
  array (
    'token_default_throttling' => 180,
    'login_with_token' => false,
    'identifier' => 'email',
    'active_delay' => 60,
    'online_delay' => 300,
    'notifications' => 
    array (
      'expiry' => '+30 days',
    ),
    'register' => 
    array (
      'autoapprove' => false,
      'notify_admins' => false,
    ),
  ),
  'base.user.token_default_throttling' => 180,
  'base.user.login_with_token' => false,
  'base.user.identifier' => 'email',
  'base.user.active_delay' => 60,
  'base.user.online_delay' => 300,
  'base.user.notifications' => 
  array (
    'expiry' => '+30 days',
  ),
  'base.user.notifications.expiry' => '+30 days',
  'base.user.register' => 
  array (
    'autoapprove' => false,
    'notify_admins' => false,
  ),
  'base.user.register.autoapprove' => false,
  'base.user.register.notify_admins' => false,
  'base.access_restriction' => 
  array (
    'redirect_on_deny' => NULL,
    'public_access' => true,
    'user_access' => true,
    'admin_access' => true,
    'firewalls' => 
    array (
      0 => 'main',
    ),
    'route_exceptions' => 
    array (
      0 => '/^(?:locale_|ux_|user_|security_)/',
    ),
    'exceptions' => 
    array (
    ),
  ),
  'base.access_restriction.redirect_on_deny' => NULL,
  'base.access_restriction.public_access' => true,
  'base.access_restriction.user_access' => true,
  'base.access_restriction.admin_access' => true,
  'base.access_restriction.firewalls' => 
  array (
    0 => 'main',
  ),
  'base.access_restriction.firewalls.0' => 'main',
  'base.access_restriction.route_exceptions' => 
  array (
    0 => '/^(?:locale_|ux_|user_|security_)/',
  ),
  'base.access_restriction.route_exceptions.0' => '/^(?:locale_|ux_|user_|security_)/',
  'base.access_restriction.exceptions' => 
  array (
  ),
  'base.site' => 
  array (
    'index' => 'app_index',
    'maintenance' => 
    array (
      'redirect_on_deny' => 'security_maintenance',
      'exception' => 
      array (
      ),
      'lockpath' => '%kernel.project_dir%/public/maintenance.lock',
    ),
  ),
  'base.site.index' => 'app_index',
  'base.site.maintenance' => 
  array (
    'redirect_on_deny' => 'security_maintenance',
    'exception' => 
    array (
    ),
    'lockpath' => '%kernel.project_dir%/public/maintenance.lock',
  ),
  'base.site.maintenance.redirect_on_deny' => 'security_maintenance',
  'base.site.maintenance.exception' => 
  array (
  ),
  'base.site.maintenance.lockpath' => '%kernel.project_dir%/public/maintenance.lock',
  'base.router' => 
  array (
    'use_custom' => NULL,
    'ip_access' => false,
    'use_fallbacks' => false,
    'fallback_warning' => true,
    'host_fallbacks' => 
    array (
    ),
  ),
  'base.router.use_custom' => NULL,
  'base.router.ip_access' => false,
  'base.router.use_fallbacks' => false,
  'base.router.fallback_warning' => true,
  'base.router.host_fallbacks' => 
  array (
  ),
  'base.translator' => 
  array (
    'politeness' => NULL,
  ),
  'base.translator.politeness' => NULL,
  'base.parameter_bag' => 
  array (
    'use_setting_bag' => NULL,
    'use_hot_bag' => NULL,
  ),
  'base.parameter_bag.use_setting_bag' => NULL,
  'base.parameter_bag.use_hot_bag' => NULL,
  'base.notifier' => 
  array (
    'mailer' => true,
    'technical_loopback' => true,
    'technical_recipient' => 
    array (
      'email' => NULL,
      'phone' => NULL,
    ),
    'admin_role' => 'ROLE_EDITOR',
    'test_recipients' => 
    array (
      0 => '@localhost$',
    ),
    'options' => 
    array (
    ),
  ),
  'base.notifier.mailer' => true,
  'base.notifier.technical_loopback' => true,
  'base.notifier.technical_recipient' => 
  array (
    'email' => NULL,
    'phone' => NULL,
  ),
  'base.notifier.technical_recipient.email' => NULL,
  'base.notifier.technical_recipient.phone' => NULL,
  'base.notifier.admin_role' => 'ROLE_EDITOR',
  'base.notifier.test_recipients' => 
  array (
    0 => '@localhost$',
  ),
  'base.notifier.test_recipients.0' => '@localhost$',
  'base.notifier.options' => 
  array (
  ),
  'base.spam' => 
  array (
    'akismet' => '',
  ),
  'base.spam.akismet' => '',
  'base.time_machine' => 
  array (
    'compression' => 'gzip',
    'time_limit' => '+30 days',
    'max_cycle' => -1,
    'snapshot_dir' => NULL,
    'excludes' => 
    array (
      0 => './vendor',
      1 => './node_modules',
      2 => './var/cache',
      3 => './var/log',
      4 => './var/coverage',
      5 => './var/phpunit',
      6 => './.git',
    ),
  ),
  'base.time_machine.compression' => 'gzip',
  'base.time_machine.time_limit' => '+30 days',
  'base.time_machine.max_cycle' => -1,
  'base.time_machine.snapshot_dir' => NULL,
  'base.time_machine.excludes' => 
  array (
    0 => './vendor',
    1 => './node_modules',
    2 => './var/cache',
    3 => './var/log',
    4 => './var/coverage',
    5 => './var/phpunit',
    6 => './.git',
  ),
  'base.time_machine.excludes.0' => './vendor',
  'base.time_machine.excludes.1' => './node_modules',
  'base.time_machine.excludes.2' => './var/cache',
  'base.time_machine.excludes.3' => './var/log',
  'base.time_machine.excludes.4' => './var/coverage',
  'base.time_machine.excludes.5' => './var/phpunit',
  'base.time_machine.excludes.6' => './.git',
  'base.icon_provider' => 
  array (
    'default_adapter' => 'Base\\Service\\Model\\IconProvider\\Adapter\\FontAwesomeAdapter',
  ),
  'base.icon_provider.default_adapter' => 'Base\\Service\\Model\\IconProvider\\Adapter\\FontAwesomeAdapter',
  'base.uploader' => 
  array (
    'warmup' => true,
    'storage' => NULL,
    'formats' => 
    array (
    ),
  ),
  'base.uploader.warmup' => true,
  'base.uploader.storage' => NULL,
  'base.uploader.formats' => 
  array (
  ),
  'base.files' => 
  array (
    'storage' => NULL,
  ),
  'base.files.storage' => NULL,
  'base.images' => 
  array (
    'max_resolution' => 'Base\\Imagine\\Filter\\Basic\\Definition\\UltraHighDefinitionFilter',
    'autorotate' => true,
    'debug' => false,
    'disable_profiler' => true,
    'max_quality' => 1,
    'timeout' => 60,
    'enable_webp' => true,
    'no_image' => 
    array (
      0 => 
      array (
        'extension' => 'webp',
        'path' => '%kernel.project_dir%/public/bundles/base/images/image.webp',
      ),
      1 => 
      array (
        'extension' => 'jpg',
        'path' => '%kernel.project_dir%/public/bundles/base/images/image.jpg',
      ),
      2 => 
      array (
        'extension' => 'jpeg',
        'path' => '%kernel.project_dir%/public/bundles/base/images/image.jpg',
      ),
      3 => 
      array (
        'extension' => 'png',
        'path' => '%kernel.project_dir%/public/bundles/base/images/image.png',
      ),
      4 => 
      array (
        'extension' => 'svg',
        'path' => '%kernel.project_dir%/public/bundles/base/images/image.svg',
      ),
      5 => 
      array (
        'extension' => 'gif',
        'path' => '%kernel.project_dir%/public/bundles/base/images/image.gif',
      ),
    ),
    'fallback' => false,
    'warmup' => true,
    'formats' => 
    array (
    ),
  ),
  'base.images.max_resolution' => 'Base\\Imagine\\Filter\\Basic\\Definition\\UltraHighDefinitionFilter',
  'base.images.autorotate' => true,
  'base.images.debug' => false,
  'base.images.disable_profiler' => true,
  'base.images.max_quality' => 1,
  'base.images.timeout' => 60,
  'base.images.enable_webp' => true,
  'base.images.no_image' => 
  array (
    0 => 
    array (
      'extension' => 'webp',
      'path' => '%kernel.project_dir%/public/bundles/base/images/image.webp',
    ),
    1 => 
    array (
      'extension' => 'jpg',
      'path' => '%kernel.project_dir%/public/bundles/base/images/image.jpg',
    ),
    2 => 
    array (
      'extension' => 'jpeg',
      'path' => '%kernel.project_dir%/public/bundles/base/images/image.jpg',
    ),
    3 => 
    array (
      'extension' => 'png',
      'path' => '%kernel.project_dir%/public/bundles/base/images/image.png',
    ),
    4 => 
    array (
      'extension' => 'svg',
      'path' => '%kernel.project_dir%/public/bundles/base/images/image.svg',
    ),
    5 => 
    array (
      'extension' => 'gif',
      'path' => '%kernel.project_dir%/public/bundles/base/images/image.gif',
    ),
  ),
  'base.images.no_image.0' => 
  array (
    'extension' => 'webp',
    'path' => '%kernel.project_dir%/public/bundles/base/images/image.webp',
  ),
  'base.images.no_image.0.extension' => 'webp',
  'base.images.no_image.0.path' => '%kernel.project_dir%/public/bundles/base/images/image.webp',
  'base.images.no_image.1' => 
  array (
    'extension' => 'jpg',
    'path' => '%kernel.project_dir%/public/bundles/base/images/image.jpg',
  ),
  'base.images.no_image.1.extension' => 'jpg',
  'base.images.no_image.1.path' => '%kernel.project_dir%/public/bundles/base/images/image.jpg',
  'base.images.no_image.2' => 
  array (
    'extension' => 'jpeg',
    'path' => '%kernel.project_dir%/public/bundles/base/images/image.jpg',
  ),
  'base.images.no_image.2.extension' => 'jpeg',
  'base.images.no_image.2.path' => '%kernel.project_dir%/public/bundles/base/images/image.jpg',
  'base.images.no_image.3' => 
  array (
    'extension' => 'png',
    'path' => '%kernel.project_dir%/public/bundles/base/images/image.png',
  ),
  'base.images.no_image.3.extension' => 'png',
  'base.images.no_image.3.path' => '%kernel.project_dir%/public/bundles/base/images/image.png',
  'base.images.no_image.4' => 
  array (
    'extension' => 'svg',
    'path' => '%kernel.project_dir%/public/bundles/base/images/image.svg',
  ),
  'base.images.no_image.4.extension' => 'svg',
  'base.images.no_image.4.path' => '%kernel.project_dir%/public/bundles/base/images/image.svg',
  'base.images.no_image.5' => 
  array (
    'extension' => 'gif',
    'path' => '%kernel.project_dir%/public/bundles/base/images/image.gif',
  ),
  'base.images.no_image.5.extension' => 'gif',
  'base.images.no_image.5.path' => '%kernel.project_dir%/public/bundles/base/images/image.gif',
  'base.images.fallback' => false,
  'base.images.warmup' => true,
  'base.images.formats' => 
  array (
  ),
  'base.paginator' => 
  array (
    'page_range' => 1,
    'page_size' => 10,
    'page_parameter' => 'page',
    'default_template' => '@Base/paginator/sliding.html.twig',
  ),
  'base.paginator.page_range' => 1,
  'base.paginator.page_size' => 10,
  'base.paginator.page_parameter' => 'page',
  'base.paginator.default_template' => '@Base/paginator/sliding.html.twig',
  'base.obfuscator' => 
  array (
    'level' => -1,
    'uuid' => '6ba7b811-9dad-11d1-80b4-00c04fd430c8',
    'encoding' => NULL,
    'max_length' => 0,
    'compression' => 'null',
    'cache_dir' => '%kernel.cache_dir%',
  ),
  'base.obfuscator.level' => -1,
  'base.obfuscator.uuid' => '6ba7b811-9dad-11d1-80b4-00c04fd430c8',
  'base.obfuscator.encoding' => NULL,
  'base.obfuscator.max_length' => 0,
  'base.obfuscator.compression' => 'null',
  'base.obfuscator.cache_dir' => '%kernel.cache_dir%',
  'base.twig' => 
  array (
    'breakpoints' => 
    array (
    ),
    'themes' => 
    array (
      'audiences' => 
      array (
      ),
      'widths' => 
      array (
      ),
      'default_audience' => NULL,
      'events' => 
      array (
      ),
      'modes' => 
      array (
        0 => 
        array (
          'name' => 'auto',
          'icon' => 'fa-solid fa-fw fa-circle-half-stroke',
        ),
        1 => 
        array (
          'name' => 'dark',
          'icon' => 'fa-solid fa-fw fa-moon',
        ),
        2 => 
        array (
          'name' => 'light',
          'icon' => 'fa-solid fa-fw fa-sun',
        ),
      ),
      'filters' => 
      array (
      ),
    ),
    'script_attributes' => 
    array (
      'defer' => false,
      'async' => false,
      'ordered' => true,
    ),
    'link_attributes' => 
    array (
      'defer' => false,
      'async' => false,
    ),
    'use_custom' => NULL,
    'use_form2' => true,
    'use_ea' => true,
    'use_bootstrap' => true,
    'autoappend' => true,
    'default_path' => '/srv/app/vendor/omnisense/omnibase/templates',
    'form_themes' => 
    array (
      0 => './form/form_div_layout.html.twig',
    ),
    'editor' => 
    array (
      'storage' => NULL,
    ),
    'paths' => 
    array (
    ),
  ),
  'base.twig.breakpoints' => 
  array (
  ),
  'base.twig.themes' => 
  array (
    'audiences' => 
    array (
    ),
    'widths' => 
    array (
    ),
    'default_audience' => NULL,
    'events' => 
    array (
    ),
    'modes' => 
    array (
      0 => 
      array (
        'name' => 'auto',
        'icon' => 'fa-solid fa-fw fa-circle-half-stroke',
      ),
      1 => 
      array (
        'name' => 'dark',
        'icon' => 'fa-solid fa-fw fa-moon',
      ),
      2 => 
      array (
        'name' => 'light',
        'icon' => 'fa-solid fa-fw fa-sun',
      ),
    ),
    'filters' => 
    array (
    ),
  ),
  'base.twig.themes.audiences' => 
  array (
  ),
  'base.twig.themes.widths' => 
  array (
  ),
  'base.twig.themes.default_audience' => NULL,
  'base.twig.themes.events' => 
  array (
  ),
  'base.twig.themes.modes' => 
  array (
    0 => 
    array (
      'name' => 'auto',
      'icon' => 'fa-solid fa-fw fa-circle-half-stroke',
    ),
    1 => 
    array (
      'name' => 'dark',
      'icon' => 'fa-solid fa-fw fa-moon',
    ),
    2 => 
    array (
      'name' => 'light',
      'icon' => 'fa-solid fa-fw fa-sun',
    ),
  ),
  'base.twig.themes.modes.0' => 
  array (
    'name' => 'auto',
    'icon' => 'fa-solid fa-fw fa-circle-half-stroke',
  ),
  'base.twig.themes.modes.0.name' => 'auto',
  'base.twig.themes.modes.0.icon' => 'fa-solid fa-fw fa-circle-half-stroke',
  'base.twig.themes.modes.1' => 
  array (
    'name' => 'dark',
    'icon' => 'fa-solid fa-fw fa-moon',
  ),
  'base.twig.themes.modes.1.name' => 'dark',
  'base.twig.themes.modes.1.icon' => 'fa-solid fa-fw fa-moon',
  'base.twig.themes.modes.2' => 
  array (
    'name' => 'light',
    'icon' => 'fa-solid fa-fw fa-sun',
  ),
  'base.twig.themes.modes.2.name' => 'light',
  'base.twig.themes.modes.2.icon' => 'fa-solid fa-fw fa-sun',
  'base.twig.themes.filters' => 
  array (
  ),
  'base.twig.script_attributes' => 
  array (
    'defer' => false,
    'async' => false,
    'ordered' => true,
  ),
  'base.twig.script_attributes.defer' => false,
  'base.twig.script_attributes.async' => false,
  'base.twig.script_attributes.ordered' => true,
  'base.twig.link_attributes' => 
  array (
    'defer' => false,
    'async' => false,
  ),
  'base.twig.link_attributes.defer' => false,
  'base.twig.link_attributes.async' => false,
  'base.twig.use_custom' => NULL,
  'base.twig.use_form2' => true,
  'base.twig.use_ea' => true,
  'base.twig.use_bootstrap' => true,
  'base.twig.autoappend' => true,
  'base.twig.default_path' => '/srv/app/vendor/omnisense/omnibase/templates',
  'base.twig.form_themes' => 
  array (
    0 => './form/form_div_layout.html.twig',
  ),
  'base.twig.form_themes.0' => './form/form_div_layout.html.twig',
  'base.twig.editor' => 
  array (
    'storage' => NULL,
  ),
  'base.twig.editor.storage' => NULL,
  'base.twig.paths' => 
  array (
  ),
  'base.attributes' => 
  array (
    'use_custom' => NULL,
    'paths' => 
    array (
      0 => '%kernel.project_dir%/src/Attribute',
    ),
  ),
  'base.attributes.use_custom' => NULL,
  'base.attributes.paths' => 
  array (
    0 => '%kernel.project_dir%/src/Attribute',
  ),
  'base.attributes.paths.0' => '%kernel.project_dir%/src/Attribute',
  'base.security' => 
  array (
    'two_factor' => 
    array (
      'required_roles' => 
      array (
      ),
      'postpone' => true,
    ),
  ),
  'base.security.two_factor' => 
  array (
    'required_roles' => 
    array (
    ),
    'postpone' => true,
  ),
  'base.security.two_factor.required_roles' => 
  array (
  ),
  'base.security.two_factor.postpone' => true,
  'base.demo' => 
  array (
    'production_database' => NULL,
    'superadmin_password' => '%env(default::DEMO_SUPERADMIN_PASSWORD)%',
    'admin_roles' => 
    array (
      0 => 'ROLE_ADMIN',
    ),
    'admin_password' => '%env(default::DEMO_ADMIN_PASSWORD)%',
    'exclude' => 
    array (
    ),
    'reset' => 
    array (
      'purge' => 
      array (
      ),
      'groups' => 
      array (
      ),
      'orphans' => true,
    ),
  ),
  'base.demo.production_database' => NULL,
  'base.demo.superadmin_password' => '%env(default::DEMO_SUPERADMIN_PASSWORD)%',
  'base.demo.admin_roles' => 
  array (
    0 => 'ROLE_ADMIN',
  ),
  'base.demo.admin_roles.0' => 'ROLE_ADMIN',
  'base.demo.admin_password' => '%env(default::DEMO_ADMIN_PASSWORD)%',
  'base.demo.exclude' => 
  array (
  ),
  'base.demo.reset' => 
  array (
    'purge' => 
    array (
    ),
    'groups' => 
    array (
    ),
    'orphans' => true,
  ),
  'base.demo.reset.purge' => 
  array (
  ),
  'base.demo.reset.groups' => 
  array (
  ),
  'base.demo.reset.orphans' => true,
  'base.opening_hours' => 
  array (
    'timezone' => 'Europe/Paris',
    'cutoff' => NULL,
    'week' => 
    array (
    ),
  ),
  'base.opening_hours.timezone' => 'Europe/Paris',
  'base.opening_hours.cutoff' => NULL,
  'base.opening_hours.week' => 
  array (
  ),
  'base.local_business' => 
  array (
    'type' => 'LocalBusiness',
    'name' => NULL,
    'url' => NULL,
    'telephone' => NULL,
    'email' => NULL,
    'image' => NULL,
    'price_range' => NULL,
    'address' => 
    array (
      'street' => NULL,
      'postal_code' => NULL,
      'locality' => NULL,
      'region' => NULL,
      'country' => NULL,
    ),
    'geo' => 
    array (
      'latitude' => NULL,
      'longitude' => NULL,
    ),
    'same_as' => 
    array (
    ),
    'area_served' => 
    array (
    ),
    'extra' => 
    array (
    ),
  ),
  'base.local_business.type' => 'LocalBusiness',
  'base.local_business.name' => NULL,
  'base.local_business.url' => NULL,
  'base.local_business.telephone' => NULL,
  'base.local_business.email' => NULL,
  'base.local_business.image' => NULL,
  'base.local_business.price_range' => NULL,
  'base.local_business.address' => 
  array (
    'street' => NULL,
    'postal_code' => NULL,
    'locality' => NULL,
    'region' => NULL,
    'country' => NULL,
  ),
  'base.local_business.address.street' => NULL,
  'base.local_business.address.postal_code' => NULL,
  'base.local_business.address.locality' => NULL,
  'base.local_business.address.region' => NULL,
  'base.local_business.address.country' => NULL,
  'base.local_business.geo' => 
  array (
    'latitude' => NULL,
    'longitude' => NULL,
  ),
  'base.local_business.geo.latitude' => NULL,
  'base.local_business.geo.longitude' => NULL,
  'base.local_business.same_as' => 
  array (
  ),
  'base.local_business.area_served' => 
  array (
  ),
  'base.local_business.extra' => 
  array (
  ),
  'base.guard' => 
  array (
    'challenge' => NULL,
    'fallback' => NULL,
    'reputation' => 
    array (
    ),
    'classifier' => NULL,
    'unreachable' => 'accept',
    'min_delay' => 3,
    'sign_in_after' => 3,
    'captcha_after' => 3,
  ),
  'base.guard.challenge' => NULL,
  'base.guard.fallback' => NULL,
  'base.guard.reputation' => 
  array (
  ),
  'base.guard.classifier' => NULL,
  'base.guard.unreachable' => 'accept',
  'base.guard.min_delay' => 3,
  'base.guard.sign_in_after' => 3,
  'base.guard.captcha_after' => 3,
  'base.signatures' => 
  array (
    'gateway' => NULL,
    'storage' => NULL,
  ),
  'base.signatures.gateway' => NULL,
  'base.signatures.storage' => NULL,
  'base.comments' => 
  array (
    'min_delay' => 4,
    'flood_interval' => 60,
  ),
  'base.comments.min_delay' => 4,
  'base.comments.flood_interval' => 60,
  'base.vault' => 
  array (
    'allow_plaintext' => false,
  ),
  'base.vault.allow_plaintext' => false,
  'base.download_links' => 
  array (
    'ttl' => 600,
  ),
  'base.download_links.ttl' => 600,
  'base.extension' => 
  array (
    'max_revisions' => 5,
    'empty_trash' => '+7 days',
    'logging_default_expiry' => 180,
  ),
  'base.extension.max_revisions' => 5,
  'base.extension.empty_trash' => '+7 days',
  'base.extension.logging_default_expiry' => 180,
  'base.screens' => 
  array (
    'version' => NULL,
  ),
  'base.screens.version' => NULL,
);
