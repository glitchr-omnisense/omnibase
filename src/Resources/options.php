<?php

/*
 * The schema of omnibase's options (base: in a Symfony site's config/packages/base.yaml, config/base.php in a
 * Laravel one), as data: Base\Options reads it, omnibridge/symfony compiles it into BaseConfiguration's tree,
 * omnibridge/laravel and a plain-PHP host validate their configuration against it with Omnibridge\Options\Processor
 * (plan "omnibase agnostic", step F5e slice 4b). The node format is Processor's: type, info, default, deprecated,
 * min/max, values; an array's children (defaults: completed when absent) or prototype (key, default_children,
 * default, deep_merging).
 *
 * `environment`: the variables a service reads through %env()% that need a value where the environment has none
 * (Symfony's env(NAME) parameter), by the group of services that reads them (Base\Services::GROUPS).
 */

use Base\Imagine\Filter\Basic\Definition\UltraHighDefinitionFilter;
use Base\Service\Model\IconProvider\Adapter\FontAwesomeAdapter;
use Base\Service\TimeMachineInterface;

$unread = ['package' => 'omnisense/omnibase', 'since' => '4.0', 'message' => 'The "%node%" option at "%path%" is read by nothing; remove it from the configuration.'];
$bool = static fn (mixed $default, ?string $info = null): array => ['type' => 'bool', 'default' => $default] + (null !== $info ? ['info' => $info] : []);
$scalar = static fn (mixed $default, ?string $info = null): array => ['type' => 'scalar', 'default' => $default] + (null !== $info ? ['info' => $info] : []);
$int = static fn (mixed $default, ?string $info = null): array => ['type' => 'int', 'default' => $default] + (null !== $info ? ['info' => $info] : []);
$strings = static fn (array $default, ?string $info = null): array => ['type' => 'array', 'prototype' => ['type' => 'scalar'], 'default' => $default] + (null !== $info ? ['info' => $info] : []);
$section = static fn (array $children): array => ['type' => 'array', 'defaults' => true, 'children' => $children];
$items = static fn (array $children, ?string $info = null): array => ['type' => 'array', 'prototype' => ['type' => 'array', 'children' => $children] + (null !== $info ? ['info' => $info] : [])];
$named = static fn (array $children): array => ['type' => 'array', 'key' => 'name', 'prototype' => ['type' => 'array', 'children' => $children]];
$bare = ['type' => 'scalar'];
$formats = ['class' => $bare, 'property' => $bare, 'width' => $bare, 'height' => $bare];
$regex = $scalar(null, 'Regex ');

return [
    'options' => [
        'autoclear' => $bool(true, 'Automatically clear cache in case of integrity issue'),

        // Plan "omnibase agnostic", step F7d: the persistence, found by what is installed (omnisense/omnibridge's
        // Persistence\Persistences) - required when omnibridge/doctrine and omnibridge/eloquent are both installed,
        // refused aloud otherwise; null runs on the only one installed.
        'persistence' => $scalar(null, 'The persistence: doctrine or eloquent (null: the only one installed; required when both are)'),

        'database' => $section([
            'fallback_warning' => $bool(true, 'Fallback warning disable in case there is no fallback'),
            'use_custom' => $bool(null, 'Use custom database settings'),
            'excluded_fields' => ['type' => 'array', 'deprecated' => $unread, 'default' => ['id', 'locale', 'translatable'],
                'prototype' => ['type' => 'scalar', 'info' => 'Global list of fields to exclude from form generation. (Default: id, locale, translatable)']],
        ]),

        'share' => ['deprecated' => $unread] + $items(['id' => $bare, 'url' => $bare]),

        'user' => $section([
            'token_default_throttling' => $scalar(3 * 60, 'Default throttling time between two tokens received'),
            'login_with_token' => $scalar(false, 'Allow using a token sent by email'),
            'identifier' => $scalar('email', 'Property used to identity user'),
            'active_delay' => $int(60, 'Threshold considering user as inactive ("active delay" is expected to be lower than "online delay")'),
            'online_delay' => $int(60 * 5, 'Threshold considering user as offline'),
            'notifications' => $section([
                'expiry' => $scalar('+30 days', 'Time before erasing data if read'),
            ]),
            'register' => $section([
                'autoapprove' => $bool(false, 'Administrator check required'),
                'notify_admins' => $bool(false, 'Administrator are notify when someone register'),
            ]),
        ]),

        'access_restriction' => $section([
            'redirect_on_deny' => ['deprecated' => $unread] + $scalar(null, 'Redirection in case of access restriction'),
            'public_access' => $bool(true, 'Access to public visitors'),
            'user_access' => $bool(true, 'Access to users'),
            'admin_access' => $bool(true, 'Access to administrators'),
            'firewalls' => ['type' => 'array', 'default_children' => true, 'prototype' => $scalar('main')],
            'route_exceptions' => $strings(['/^(?:locale_|ux_|user_|security_)/'], 'Routes (regular expressions on their names) no access restriction stops, and the referrer never remembers: the sign-in pages, the language switch, the UX endpoints.'),
            'exceptions' => ['type' => 'array', 'prototype' => $section([
                'locale' => $scalar(null),
                'env' => $scalar(null),
                'host' => $regex,
                'path' => $regex,
                'domain' => $regex,
                'subdomain' => $regex,
                'scheme' => $regex,
            ])],
        ]),

        'site' => $section([
            'index' => $scalar('app_index', 'Website home page'),
            'maintenance' => $section([
                'redirect_on_deny' => $scalar('security_maintenance', 'Maintenance redirection'),
                'exception' => ['type' => 'array', 'deprecated' => $unread, 'key' => 'code', 'prototype' => $bare, 'default' => []],
                'lockpath' => $scalar('%kernel.project_dir%/public/maintenance.lock', 'Maintenance lock file location'),
            ]),
        ]),

        'router' => $section([
            'use_custom' => $bool(null, 'Use custom router'),
            'ip_access' => ['deprecated' => ['message' => 'The "%node%" option at "%path%" has no effect (RouterSubscriber reads it only once isGranted(\'VALIDATE_IP\') holds, which no voter of the core decides); remove it from the configuration.'] + $unread] + $bool(false, 'Allow accessing by'),
            'use_fallbacks' => $bool(false, 'Restrict to permitted hosts'),
            'fallback_warning' => $bool(true, 'Fallback warning disable in case there is no fallback'),
            'host_fallbacks' => ['type' => 'array', 'prototype' => $section([
                'locale' => $scalar(null),
                'env' => $scalar('%env(APP_ENV)%'),
                'reduction' => $bool(null),
                'subdomain' => ['type' => 'mixed', 'info' => 'Sub-domain', 'default' => []],
                'machine' => ['type' => 'mixed', 'info' => 'Machine name', 'default' => []],
                'domain' => ['type' => 'mixed', 'info' => 'Domain', 'default' => []],
                'port' => ['type' => 'mixed', 'info' => 'Port number', 'default' => [80]],
                'base_dir' => $scalar('/', 'Base directory'),
            ])],
        ]),

        'translator' => $section([
            'politeness' => ['type' => 'enum', 'values' => [null, 'plain', 'polite', 'formal'], 'default' => null,
                'info' => 'How the site addresses people (docs/20-architecture/politeness.md): a text asked without a level of its own is taken in its "._plain", "._polite" or "._formal" variant when the catalogue has one (formal falls back on polite), else as it is. Unset: the texts as they are written.'],
        ]),

        'parameter_bag' => $section([
            'use_setting_bag' => $bool(null, 'Use setting bag from database'),
            'use_hot_bag' => $bool(null, 'Use hot bag parameter rewriting'),
        ]),

        'notifier' => $section([
            'mailer' => $bool(true, 'Enable mailer'),
            'technical_loopback' => $bool(true, 'Do not allow sending email until loopback is removed'),
            'technical_recipient' => $section([
                'email' => $scalar(null, 'Technical contact email'),
                'phone' => $scalar(null, 'Default phone number'),
            ]),
            'admin_role' => $scalar('ROLE_EDITOR', 'Administrators receiving notifications'),
            'test_recipients' => ['type' => 'array', 'default_children' => true, 'prototype' => $scalar('@localhost$')],
            'options' => $items(['channel' => $bare, 'markAsRead' => $bare]),
        ]),

        'spam' => $section([
            'akismet' => $scalar('', 'AKISMET API Key'),
        ]),

        'time_machine' => $section([
            'compression' => $scalar('gzip', 'Default compression algorithm'),
            'time_limit' => $scalar('+30 days', 'Default time limit before deleting'),
            'max_cycle' => $int(-1, 'Default compression algorithm'),
            'snapshot_dir' => $scalar(null, 'Directory the snapshot is staged in before transfer (defaults to the kernel cache directory)'),
            'excludes' => $strings(TimeMachineInterface::DEFAULT_EXCLUDES, 'Paths left out of the application tarball'),
        ]),

        'icon_provider' => $section([
            'default_adapter' => $scalar(FontAwesomeAdapter::class, 'Default icon provider class'),
        ]),

        'uploader' => $section([
            'warmup' => ['deprecated' => $unread] + $bool(true, 'Automatic image uploader warmup'),
            // The storage the core declares for the uploads (discovery.php's `storages`), each host's where none is configured.
            'storage' => $scalar('local.uploads', 'Upload storage location'),
            'formats' => $items($formats, 'Specific formats'),
        ]),

        'files' => $section([
            'storage' => ['deprecated' => $unread] + $scalar(null),
        ]),

        'images' => $section([
            'max_resolution' => $scalar(UltraHighDefinitionFilter::class),
            'autorotate' => ['deprecated' => $unread] + $bool(true),
            'debug' => $bool(false),
            'disable_profiler' => ['deprecated' => $unread] + $bool(true),
            'max_quality' => $scalar(1),
            'timeout' => $scalar(60),
            'enable_webp' => $scalar(true),
            'no_image' => ['default' => [
                ['extension' => 'webp', 'path' => '%kernel.project_dir%/public/bundles/base/images/image.webp'],
                ['extension' => 'jpg', 'path' => '%kernel.project_dir%/public/bundles/base/images/image.jpg'],
                ['extension' => 'jpeg', 'path' => '%kernel.project_dir%/public/bundles/base/images/image.jpg'],
                ['extension' => 'png', 'path' => '%kernel.project_dir%/public/bundles/base/images/image.png'],
                ['extension' => 'svg', 'path' => '%kernel.project_dir%/public/bundles/base/images/image.svg'],
                ['extension' => 'gif', 'path' => '%kernel.project_dir%/public/bundles/base/images/image.gif'],
            ]] + $items(['extension' => $bare, 'path' => $bare], 'Replacement image'),
            'fallback' => $bool(false),
            'warmup' => ['deprecated' => $unread] + $scalar(true),
            'formats' => ['deprecated' => $unread] + $items($formats, 'Specific formats'),
        ]),

        'paginator' => $section([
            'page_range' => $int(1, 'Range around the current page'),
            'page_size' => $int(10, 'Number of element per page'),
            'page_parameter' => $scalar('page', 'Default parameter name in route'),
            'default_template' => $scalar('@Base/paginator/sliding.html.twig', 'Default template used to display pages in sliding control'),
        ]),

        'obfuscator' => $section([
            'level' => $int(-1, 'Compression level'),
            // Symfony\Component\Uid\Uuid::NAMESPACE_URL, written out: the schema loads no class.
            'uuid' => $scalar('6ba7b811-9dad-11d1-80b4-00c04fd430c8', 'Use uuid shortening'),
            'encoding' => $int(null, 'Compression encoding'),
            'max_length' => $int(0, 'Decoding max length'),
            'compression' => $scalar('null', 'Compression algorithm'),
            'cache_dir' => $scalar('%kernel.cache_dir%', 'Identifier-map storage directory; must survive cache:clear/deploys or previously-issued short URLs become permanently unresolvable'),
        ]),

        'twig' => $section([
            'breakpoints' => $items(['name' => $bare, 'media' => $bare]),
            'themes' => $section([
                'audiences' => $named(['age' => $int(null), 'icon' => $scalar(null)]),
                'widths' => $named(['icon' => $scalar(null), 'class' => $scalar(null)]),
                'default_audience' => $scalar(null, 'Default audience key among valid audiences'),
                'events' => $named(['begin' => $scalar(null), 'end' => $scalar(null), 'priority' => $int(0), 'icon' => $scalar(null)]),
                'modes' => ['default' => [
                    ['name' => 'auto', 'icon' => 'fa-solid fa-fw fa-circle-half-stroke'],
                    ['name' => 'dark', 'icon' => 'fa-solid fa-fw fa-moon'],
                    ['name' => 'light', 'icon' => 'fa-solid fa-fw fa-sun'],
                ]] + $named(['class' => $scalar(null), 'icon' => $scalar(null), 'prefers' => $scalar(null)]),
                'filters' => $named(['icon' => $scalar(null), 'filter' => $scalar(null)]),
            ]),
            'script_attributes' => $section([
                'defer' => $scalar(false),
                'async' => $scalar(false),
                'ordered' => $bool(true, 'Scripts run in the order of the page: a file named -async is loaded defer. The core\'s base-async sets window.jQuery, which the site\'s entries read as an external; false brings back async, with no guarantee that it runs first.'),
            ]),
            'link_attributes' => $section([
                'defer' => $scalar(false),
                'async' => $scalar(false),
            ]),
            'use_custom' => $bool(null, 'Use base filesystem loader'),
            'use_form2' => $bool(true, 'Use custom base form style'),
            'use_ea' => $bool(true, 'Include EA form style'),
            'use_bootstrap' => $bool(true, 'Use bootstrap style in forms'),
            'autoappend' => $bool(true, 'Autoappend required dependencies in html content'),
            // The templates/ of this package, wherever Composer installed it.
            'default_path' => $scalar(\dirname(__DIR__, 2).'/templates', 'Default twig path: the templates/ of this package, wherever Composer installed it (vendor/omnisense/omnibase, a path repository, a checkout)'),
            'form_themes' => ['type' => 'array', 'default_children' => true, 'prototype' => $scalar('./form/form_div_layout.html.twig')],
            'editor' => $section([
                // The storage the core declares for the editor's files (discovery.php's `storages`).
                'storage' => $scalar('local.wysiwyg', 'The editor\'s files\' storage'),
            ]),
            'paths' => $items(['path' => $bare, 'namespace' => $bare]),
        ]),

        'attributes' => $section([
            'use_custom' => $bool(null, 'Use custom attribute reader'),
            'paths' => ['type' => 'array', 'default_children' => true, 'prototype' => $scalar('%kernel.project_dir%/src/Attribute')],
        ]),

        // Account security decided by the application (the administrator's own switches are settings: Base\Service\SecurityPolicy).
        'security' => $section([
            'two_factor' => $section([
                // A list is merged by appending: when@test's [] left the base list as it was, and when@dev's [ROLE_ADMIN]
                // gave [ROLE_STAFF, ROLE_ADMIN]. The last definition is the list.
                'required_roles' => ['deep_merging' => false] + $strings([], 'Roles whose holders must have a second factor (directly or through the role hierarchy), e.g. [ROLE_STAFF]. Empty: the administrator\'s "mandatory for everyone" setting alone decides. A later definition (when@dev, when@test, another file) replaces the list, it does not add to it.'),
                'postpone' => $bool(true, 'Whether the enrolment prompt offers a "not now" (kept for the session). False: an account a second factor is required of is sent back to the prompt on every page until it has one.'),
            ]),
        ]),

        // The `demo` environment (docs/20-architecture/demo.md). Read in every environment, acted on in `demo` only.
        'demo' => $section([
            'production_database' => $scalar(null, 'The DSN of the production database ("mysql://host:3306/name"; user and password are not compared). The demo environment refuses to start on it - and refuses to start while this is unset.'),
            'superadmin_password' => $scalar('%env(default::DEMO_SUPERADMIN_PASSWORD)%', 'The super-administrator\'s password in the demo environment: a secret (DEMO_SUPERADMIN_PASSWORD). Unset or empty: no account whose roles reach ROLE_SUPERADMIN can sign in there.'),
            'admin_roles' => $strings(['ROLE_ADMIN'], 'The roles that open the back office. In the demo environment an account whose roles reach one of them is not offered to visitors - neither in the banner nor on the sign-in page - and signs in with base.demo.admin_password or not at all. Empty: the back office is as public as the rest of the demonstration.'),
            'admin_password' => $scalar('%env(default::DEMO_ADMIN_PASSWORD)%', 'The password of the back office\'s accounts in the demo environment: a secret (DEMO_ADMIN_PASSWORD), given to whoever is shown the back office. Unset or empty: no account whose roles reach base.demo.admin_roles can sign in there.'),
            'exclude' => $strings([], 'Identifiers of declared demonstration accounts this site leaves out.'),
            'reset' => $section([
                'purge' => $strings([], 'Directories demo:reset empties before it reloads the fixtures: the files visitors sent to a storage of the application\'s own.'),
                'groups' => $strings([], 'The fixture groups demo:reset loads (doctrine:fixtures:load --group). Empty: every fixture.'),
                'orphans' => $bool(true, 'Whether demo:reset deletes the uploaded files no row names any more (uploader:entities --delete-orphans).'),
            ]),
        ]),

        // The shared bricks (docs/40-commons): opening hours, comments, signed downloads.
        'opening_hours' => $section([
            'timezone' => $scalar('Europe/Paris', 'The timezone the hours are given in.'),
            'cutoff' => $scalar(null, '"HH:MM": past it, orderDay() is the next open day. Null: the day itself until it closes.'),
            'week' => ['type' => 'mixed', 'default' => [], 'info' => 'The usual week until one is saved (hoursWeekDay): ISO day (1 = Monday) => [["09:00", "13:00"], ...].'],
        ]),

        // schema.org's LocalBusiness (docs/40-commons/local-business.md): what the back office's settings do not say.
        'local_business' => $section([
            'type' => $scalar('LocalBusiness', 'The schema.org type: LocalBusiness, or one of its kinds (Restaurant, Store, Dentist, ProfessionalService...).'),
            'name' => $scalar(null, 'Null: the site\'s title (base.settings.title).'),
            'url' => $scalar(null, 'Null: the site\'s home address.'),
            'telephone' => $scalar(null, 'Null: the setting base.settings.phone.'),
            'email' => $scalar(null),
            'image' => $scalar(null, 'A path under public/ ("assets/ico/android-chrome-512x512.png") or an address.'),
            'price_range' => $scalar(null, '"€€", "10-50 €"...'),
            'address' => $section([
                'street' => $scalar(null),
                'postal_code' => $scalar(null),
                'locality' => $scalar(null),
                'region' => $scalar(null),
                'country' => $scalar(null, 'ISO 3166-1 alpha-2: FR.'),
            ]),
            'geo' => $section([
                'latitude' => ['type' => 'float', 'default' => null],
                'longitude' => ['type' => 'float', 'default' => null],
            ]),
            'same_as' => $strings([], 'The business elsewhere: its Facebook, Instagram, LinkedIn pages.'),
            'area_served' => $strings([]),
            'extra' => ['type' => 'mixed', 'default' => [], 'info' => 'Any other schema.org property, merged as it is: {parentOrganization: {"@type": Organization, name: PANO}}.'],
        ]),

        // The forms' guard (Base\Service\FormGuard, the option `guard` of a form; docs/20-architecture/guard.md).
        'guard' => $section([
            'challenge' => $scalar(null, 'The captcha: a gateway of omnishield.gateways. Null: omnishield.challenge.gateway when omnisense/omnishield is installed; false: none.'),
            'fallback' => $scalar(null, 'The captcha shown to a visitor who refused the third party the captcha reaches (consent): a gateway that reaches nobody, ALTCHA.'),
            'reputation' => $strings([], 'The lists asked about whoever submits (IP address, e-mail, name): gateways of omnishield.gateways - disposable, stopforumspam.'),
            'classifier' => $scalar(null, 'The classifier of what is written (SpamChecker): a gateway of omnishield.gateways. Null: Akismet with the key api.spam.akismet (base.spam.akismet) when omnishield/akismet is installed.'),
            'unreachable' => ['type' => 'enum', 'values' => ['accept', 'reject'], 'default' => 'accept', 'info' => 'A list or a classifier that does not answer: the form goes on (accept) or is refused (reject). The captcha\'s is omnishield.challenge.unreachable.'],
            'min_delay' => ['min' => 0] + $int(3, 'Seconds between a guarded form being shown and sent: faster is a robot. 0: not checked.'),
            'sign_in_after' => ['min' => 0] + $int(3, 'Failed sign-ins from an address before the sign-in form asks the captcha too. 0: never.'),
            'captcha_after' => ['min' => 0] + $int(3, 'Refused tries of a guarded form from a visitor (address, else session; 15 minutes) before its captcha is shown and asked. 0: always shown. A form sets its own with the option guard.captcha_after.'),
        ]),

        // Documents signed through omnisense/omnisign (Base\Service\Signatures): only with that family.
        'signatures' => $section([
            'gateway' => $scalar(null, 'The gateway envelopes go through: one of omnisign.gateways. Null: the only one configured.'),
            'storage' => $scalar(null, 'The private Flysystem storage the signed documents and their evidence are kept in. Null: the uploads\' (base.uploader.storage).'),
        ]),

        'comments' => $section([
            'min_delay' => ['min' => 0] + $int(4, 'Seconds between a comment form being opened and sent: faster is a robot.'),
            'flood_interval' => ['min' => 0] + $int(60, 'Seconds between two comments from the same address.'),
        ]),

        'vault' => $section([
            'allow_plaintext' => $bool(false, '#[Vault]: without the environment\'s key pair (bin/console secrets:generate-keys), store a secured field in clear instead of refusing it. Off: the save fails with VaultKeyNotFoundException.'),
        ]),

        'download_links' => $section([
            'ttl' => ['min' => 1] + $int(600, 'Seconds a signed download link stays valid.'),
        ]),

        'extension' => $section([
            'max_revisions' => $scalar(5, 'Max number of revision for a given entity'),
            // "+7 days", what TrasheableSubscriber and TrashPurgeCommand say; 5 was given to new DateTime() and failed to
            // parse, so a host without its own value could not remove a Thread.
            'empty_trash' => $scalar('+7 days', 'Time a trashed entity is kept before the purge may delete it for good: a relative date PHP reads ("+7 days", "+1 month"); a bare number is a number of days'),
            'logging_default_expiry' => ['deprecated' => $unread] + $scalar(3 * 60, 'Default logging expiry'),
        ]),

        // The screens (plan "omnibase agnostique", C5): pages handed as a template and a view model.
        'screens' => $section([
            'version' => $scalar(null, 'The version of the front end that renders the screens (omnibridge/react\'s X-Omnibridge-Version): a front end of another version reloads the page whole. Null: none checked.'),
        ]),
    ],

    'environment' => [
        // An empty default for each: the container still compiles for an application with no deployed relay, collab_live
        // stays optional, and CollabTicketFactory::isConfigured() refuses a ticket unless both are set.
        'COLLAB_TICKET_SECRET' => ['group' => 'collab', 'default' => ''],
        'COLLAB_RELAY_WS_URL' => ['group' => 'collab', 'default' => ''],
        // Live unless the application says otherwise: BASE_TRADING_LIVE=0 keeps every rate to the stored ones but for the
        // calls that ask for the providers (a free plan's monthly quota is spent otherwise). Read by the core's Trading.
        'BASE_TRADING_LIVE' => ['group' => 'media', 'default' => 'true'],
    ],
];
