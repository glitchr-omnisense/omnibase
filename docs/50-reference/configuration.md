---
title: Configuration reference
order: 10
---

# Configuration reference

Everything under the `base:` key of `config/packages/base.yaml`, as
`Base\DependencyInjection\BaseConfiguration` declares it, with its type, its
default and what reads it. The values are processed once, when the container is
built, by `Base\DependencyInjection\BaseExtension::load()`.

## How the values are read

`BaseExtension` does not hand the processed tree to services one by one: it
sets **every node as a container parameter** named after its path, `base.` and
the keys joined by dots (`AbstractBaseExtension::setConfiguration()`). An array
is set both whole and leaf by leaf: `base.time_machine.excludes` is the list,
`base.time_machine.excludes.0` its first entry. The code reads them through
`Base\Service\ParameterBagInterface` (`$parameterBag->get('base.uploader.storage')`),
`%base.download_links.ttl%` in a service definition, or
`Base\Contract\Runtime::parameters()` where there is no container (an entity's
getter).

Under Symfony the parameter bag is `Base\Service\HotParameterBag`: the settings
stored in the database are laid over the container's parameters (see
[The parameters and the settings over them](../20-architecture/contracts.md#the-parameters-and-the-settings-over-them-step-c9)).
A path the configuration does not declare reads as `null`.

## The smallest configuration

The harness's application (`docker/app/config/packages/base.yaml`) runs on this:

```yaml
base:
    parameter_bag:
        use_hot_bag: true
        use_setting_bag: true
    database:
        use_custom: true
    router:
        use_custom: true
    attributes:
        use_custom: true
    twig:
        use_custom: true
        use_form2: true
        use_bootstrap: true
        editor:
            storage: "local.wysiwyg"
    uploader:
        storage: "local.uploads"
    site:
        index: demo_index
```

The four `use_custom` flags and the two `parameter_bag` flags default to `null`
(off): a site that leaves them out runs without omnibase's router, Twig loader,
attribute reader and Doctrine features, without the settings stored in the
database, and without those settings laid over the parameters (`HotParameters`
reads the configuration whether the overlay is on or not). The storages are Flysystem storages of the site's own
(`config/packages/flysystem.yaml`).

## Dumping the tree
`bin/console config:dump-reference base` dumps it, and `debug:config base` the site's values
(`BaseExtension::getConfiguration()`). The tree below was dumped in the core's harness with
Symfony's reference dumper, from this script (the image's vendor, the core mounted over its
copy):

```php
<?php
// dump.php
require '/srv/demo/vendor/autoload.php';

use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;

echo (new YamlReferenceDumper())->dump(new Base\DependencyInjection\BaseConfiguration());
```

```bash
docker run --rm --network none \
    -v "$PWD":/srv/demo/vendor/omnisense/omnibase:ro -v "$PWD/var/scratch":/scratch:ro \
    --entrypoint php omnisense/omnibase-harness:latest /scratch/dump.php
```

## The full tree, with its defaults

The options read by nothing are deprecated (`excluded_fields`, `share`,
`access_restriction.redirect_on_deny`, `site.maintenance.exception`, `uploader.warmup`,
`files.storage`, `images.autorotate`, `images.disable_profiler`, `images.warmup`,
`images.formats`, `extension.logging_default_expiry`): a site that sets one gets Symfony's
deprecation notice, and they go at the next major.

`twig.default_path` is the package's own `templates/` wherever Composer put it:
`/srv/demo/vendor/omnisense/omnibase/templates` is the harness's.

```yaml
base:

    # Automatically clear cache in case of integrity issue
    autoclear:            true
    database:

        # Fallback warning disable in case there is no fallback
        fallback_warning:     true

        # Use custom database settings
        use_custom:           null
        excluded_fields:

            # Defaults:
            - id
            - locale
            - translatable
    share:

        # Prototype
        -
            id:                   ~
            url:                  ~
    user:

        # Default throttling time between two tokens received
        token_default_throttling: 180

        # Allow using a token sent by email
        login_with_token:     false

        # Property used to identity user
        identifier:           email

        # Threshold considering user as inactive ("active delay" is expected to be lower than "online delay")
        active_delay:         60

        # Threshold considering user as offline
        online_delay:         300
        notifications:

            # Time before erasing data if read
            expiry:               '+30 days'
        register:

            # Administrator check required
            autoapprove:          false

            # Administrator are notify when someone register
            notify_admins:        false
    access_restriction:

        # Redirection in case of access restriction
        redirect_on_deny:     null

        # Access to public visitors
        public_access:        true

        # Access to users
        user_access:          true

        # Access to administrators
        admin_access:         true
        firewalls:

            # Default:
            - main

        # Routes (regular expressions on their names) no access restriction stops, and the referrer never remembers: the sign-in pages, the language switch, the UX endpoints.
        route_exceptions:

            # Default:
            - /^(?:locale_|ux_|user_|security_)/
        exceptions:

            # Prototype
            -
                locale:               null
                env:                  null

                # Regex 
                host:                 null

                # Regex 
                path:                 null

                # Regex 
                domain:               null

                # Regex 
                subdomain:            null

                # Regex 
                scheme:               null
    site:

        # Website home page
        index:                app_index
        maintenance:

            # Maintenance redirection
            redirect_on_deny:     security_maintenance
            exception:

                # Prototype
                code:                 ~

            # Maintenance lock file location
            lockpath:             '%kernel.project_dir%/public/maintenance.lock'
    router:

        # Use custom router
        use_custom:           null

        # Allow accessing by
        ip_access:            false

        # Restrict to permitted hosts
        use_fallbacks:        false

        # Fallback warning disable in case there is no fallback
        fallback_warning:     true
        host_fallbacks:

            # Prototype
            -
                locale:               null
                env:                  '%env(APP_ENV)%'
                reduction:            null

                # Sub-domain
                subdomain:            []

                # Machine name
                machine:              []

                # Domain
                domain:               []

                # Port number
                port:

                    # Default:
                    - 80

                # Base directory
                base_dir:             /
    translator:

        # How the site addresses people (docs/20-architecture/politeness.md): a text asked without a level of its own is taken in its "._plain", "._polite" or "._formal" variant when the catalogue has one (formal falls back on polite), else as it is. Unset: the texts as they are written.
        politeness:           null # One of null; "plain"; "polite"; "formal"
    parameter_bag:

        # Use setting bag from database
        use_setting_bag:      null

        # Use hot bag parameter rewriting
        use_hot_bag:          null
    notifier:

        # Enable mailer
        mailer:               true

        # Do not allow sending email until loopback is removed
        technical_loopback:   true
        technical_recipient:

            # Technical contact email
            email:                null

            # Default phone number
            phone:                null

        # Administrators receiving notifications
        admin_role:           ROLE_EDITOR
        test_recipients:

            # Default:
            - @localhost$
        options:

            # Prototype
            -
                channel:              ~
                markAsRead:           ~
    spam:

        # AKISMET API Key
        akismet:              ''
    time_machine:

        # Default compression algorithm
        compression:          gzip

        # Default time limit before deleting
        time_limit:           '+30 days'

        # Default compression algorithm
        max_cycle:            -1

        # Directory the snapshot is staged in before transfer (defaults to the kernel cache directory)
        snapshot_dir:         null

        # Paths left out of the application tarball
        excludes:

            # Defaults:
            - ./vendor
            - ./node_modules
            - ./var/cache
            - ./var/log
            - ./var/coverage
            - ./var/phpunit
            - ./.git
    icon_provider:

        # Default icon provider class
        default_adapter:      Base\Service\Model\IconProvider\Adapter\FontAwesomeAdapter
    uploader:

        # Automatic image uploader warmup
        warmup:               true

        # Upload storage location
        storage:              null
        formats:

            # Prototype: Specific formats
            -
                class:                ~
                property:             ~
                width:                ~
                height:               ~
    files:
        storage:              null
    images:
        max_resolution:       Base\Imagine\Filter\Basic\Definition\UltraHighDefinitionFilter
        autorotate:           true
        debug:                false
        disable_profiler:     true
        max_quality:          1
        timeout:              60
        enable_webp:          true
        no_image:

            # Prototype: Replacement image
            -
                extension:            ~
                path:                 ~
        fallback:             false
        warmup:               true
        formats:

            # Prototype: Specific formats
            -
                class:                ~
                property:             ~
                width:                ~
                height:               ~
    paginator:

        # Range around the current page
        page_range:           1

        # Number of element per page
        page_size:            10

        # Default parameter name in route
        page_parameter:       page

        # Default template used to display pages in sliding control
        default_template:     '@Base/paginator/sliding.html.twig'
    obfuscator:

        # Compression level
        level:                -1

        # Use uuid shortening
        uuid:                 6ba7b811-9dad-11d1-80b4-00c04fd430c8

        # Compression encoding
        encoding:             null

        # Decoding max length
        max_length:           0

        # Compression algorithm
        compression:          'null'

        # Identifier-map storage directory; must survive cache:clear/deploys or previously-issued short URLs become permanently unresolvable
        cache_dir:            '%kernel.cache_dir%'
    twig:
        breakpoints:

            # Prototype
            -
                name:                 ~
                media:                ~
        themes:
            audiences:

                # Prototype
                name:
                    age:                  null
                    icon:                 null
            widths:

                # Prototype
                name:
                    icon:                 null
                    class:                null

            # Default audience key among valid audiences
            default_audience:     null
            events:

                # Prototype
                name:
                    begin:                null
                    end:                  null
                    priority:             0
                    icon:                 null
            modes:

                # Prototype
                name:
                    class:                null
                    icon:                 null
                    prefers:              null
            filters:

                # Prototype
                name:
                    icon:                 null
                    filter:               null
        script_attributes:
            defer:                false
            async:                false

            # Scripts run in the order of the page: a file named -async is loaded defer. The core's base-async sets window.jQuery, which the site's entries read as an external; false brings back async, with no guarantee that it runs first.
            ordered:              true
        link_attributes:
            defer:                false
            async:                false

        # Use base filesystem loader
        use_custom:           null

        # Use custom base form style
        use_form2:            true

        # Include EA form style
        use_ea:               true

        # Use bootstrap style in forms
        use_bootstrap:        true

        # Autoappend required dependencies in html content
        autoappend:           true

        # Default twig path: the templates/ of this package, wherever Composer installed it (vendor/omnisense/omnibase, a path repository, a checkout)
        default_path:         /srv/demo/vendor/omnisense/omnibase/templates
        form_themes:

            # Default:
            - ./form/form_div_layout.html.twig
        editor:
            storage:              null
        paths:

            # Prototype
            -
                path:                 ~
                namespace:            ~
    attributes:

        # Use custom attribute reader
        use_custom:           null
        paths:

            # Default:
            - %kernel.project_dir%/src/Attribute
    security:
        two_factor:

            # Roles whose holders must have a second factor (directly or through the role hierarchy), e.g. [ROLE_STAFF]. Empty: the administrator's "mandatory for everyone" setting alone decides. A later definition (when@dev, when@test, another file) replaces the list, it does not add to it.
            required_roles:       []

            # Whether the enrolment prompt offers a "not now" (kept for the session). False: an account a second factor is required of is sent back to the prompt on every page until it has one.
            postpone:             true
    demo:

        # The DSN of the production database ("mysql://host:3306/name"; user and password are not compared). The demo environment refuses to start on it - and refuses to start while this is unset.
        production_database:  null

        # The super-administrator's password in the demo environment: a secret (DEMO_SUPERADMIN_PASSWORD). Unset or empty: no account whose roles reach ROLE_SUPERADMIN can sign in there.
        superadmin_password:  '%env(default::DEMO_SUPERADMIN_PASSWORD)%'

        # The roles that open the back office. In the demo environment an account whose roles reach one of them is not offered to visitors - neither in the banner nor on the sign-in page - and signs in with base.demo.admin_password or not at all. Empty: the back office is as public as the rest of the demonstration.
        admin_roles:

            # Default:
            - ROLE_ADMIN

        # The password of the back office's accounts in the demo environment: a secret (DEMO_ADMIN_PASSWORD), given to whoever is shown the back office. Unset or empty: no account whose roles reach base.demo.admin_roles can sign in there.
        admin_password:       '%env(default::DEMO_ADMIN_PASSWORD)%'

        # Identifiers of declared demonstration accounts this site leaves out.
        exclude:              []
        reset:

            # Directories demo:reset empties before it reloads the fixtures: the files visitors sent to a storage of the application's own.
            purge:                []

            # The fixture groups demo:reset loads (doctrine:fixtures:load --group). Empty: every fixture.
            groups:               []

            # Whether demo:reset deletes the uploaded files no row names any more (uploader:entities --delete-orphans).
            orphans:              true
    opening_hours:

        # The timezone the hours are given in.
        timezone:             Europe/Paris

        # "HH:MM": past it, orderDay() is the next open day. Null: the day itself until it closes.
        cutoff:               null

        # The usual week until one is saved (hoursWeekDay): ISO day (1 = Monday) => [["09:00", "13:00"], ...].
        week:                 []
    local_business:

        # The schema.org type: LocalBusiness, or one of its kinds (Restaurant, Store, Dentist, ProfessionalService...).
        type:                 LocalBusiness

        # Null: the site's title (base.settings.title).
        name:                 null

        # Null: the site's home address.
        url:                  null

        # Null: the setting base.settings.phone.
        telephone:            null
        email:                null

        # A path under public/ ("assets/ico/android-chrome-512x512.png") or an address.
        image:                null

        # "€€", "10-50 €"...
        price_range:          null
        address:
            street:               null
            postal_code:          null
            locality:             null
            region:               null

            # ISO 3166-1 alpha-2: FR.
            country:              null
        geo:
            latitude:             null
            longitude:            null

        # The business elsewhere: its Facebook, Instagram, LinkedIn pages.
        same_as:              []
        area_served:          []

        # Any other schema.org property, merged as it is: {parentOrganization: {"@type": Organization, name: PANO}}.
        extra:                []
    guard:

        # The captcha: a gateway of omnishield.gateways. Null: omnishield.challenge.gateway when glitchr/omnishield is installed; false: none.
        challenge:            null

        # The captcha shown to a visitor who refused the third party the captcha reaches (consent): a gateway that reaches nobody, ALTCHA.
        fallback:             null

        # The lists asked about whoever submits (IP address, e-mail, name): gateways of omnishield.gateways - disposable, stopforumspam.
        reputation:           []

        # The classifier of what is written (SpamChecker): a gateway of omnishield.gateways. Null: Akismet with the key api.spam.akismet (base.spam.akismet) when omnishield/akismet is installed.
        classifier:           null

        # A list or a classifier that does not answer: the form goes on (accept) or is refused (reject). The captcha's is omnishield.challenge.unreachable.
        unreachable:          accept # One of "accept"; "reject"

        # Seconds between a guarded form being shown and sent: faster is a robot. 0: not checked.
        min_delay:            3

        # Failed sign-ins from an address before the sign-in form asks the captcha too. 0: never.
        sign_in_after:        3

        # Refused tries of a guarded form from a visitor (address, else session; 15 minutes) before its captcha is shown and asked. 0: always shown. A form sets its own with the option guard.captcha_after.
        captcha_after:        3
    signatures:

        # The gateway envelopes go through: one of omnisign.gateways. Null: the only one configured.
        gateway:              null

        # The private Flysystem storage the signed documents and their evidence are kept in. Null: the uploads' (base.uploader.storage).
        storage:              null
    comments:

        # Seconds between a comment form being opened and sent: faster is a robot.
        min_delay:            4

        # Seconds between two comments from the same address.
        flood_interval:       60
    vault:

        # #[Vault]: without the environment's key pair (bin/console secrets:generate-keys), store a secured field in clear instead of refusing it. Off: the save fails with VaultKeyNotFoundException.
        allow_plaintext:      false
    download_links:

        # Seconds a signed download link stays valid.
        ttl:                  600
    extension:

        # Max number of revision for a given entity
        max_revisions:        5

        # Time a trashed entity is kept before the purge may delete it for good: a relative date PHP reads ("+7 days", "+1 month"); a bare number is a number of days
        empty_trash:          '+7 days'

        # Default logging expiry
        logging_default_expiry: 180
    screens:

        # The version of the front end that renders the screens (omnibridge/react's X-Omnibridge-Version): a front end of another version reloads the page whole. Null: none checked.
        version:              null
```

## Top level

| Option | Type | Default | Effect |
|---|---|---|---|
| `autoclear` | bool | `true` | On the first `cache:clear` of an installation, the command runs `cache:clear` a second time in a process of its own (`CacheClearTrait::doubleCacheClear()`); in debug, when webpack's `entrypoints.json` changed during a request, `WebpackTagRenderer` clears the cache instead of throwing "Entrypoint ... got modified". |

## `database`

| Option | Type | Default | Effect |
|---|---|---|---|
| `use_custom` | bool | `null` | omnibase's Doctrine features: `BaseBundle::boot()` registers its types over DBAL's (the UTC `datetime`, see [Time and time zones](../20-architecture/time.md)); `cache:clear` reports it. |
| `fallback_warning` | bool | `true` | `cache:clear` warns when the entity manager's metadata factory is not omnibase's `ClassMetadataFactory`. |
| `excluded_fields` | list of strings | `[id, locale, translatable]` | Deprecated, read by nothing: the translation fields take their own `excluded_fields` option (`TranslationType`, `TranslationField`). |

## `share`

A list of `{id, url}`. Deprecated, read by nothing: the sharing buttons come from the
adapters tagged `base.service.sharing` (`Base\Service\Model\Sharing\SharingAdapterInterface`).

## `user`

| Option | Type | Default | Effect |
|---|---|---|---|
| `token_default_throttling` | int (seconds) | `180` | The throttle a `Base\Entity\User\Token` gets when it is built without one: the time before the same token may be sent again. |
| `login_with_token` | bool | `false` | Whether the sign-in page offers a link by e-mail (`allow_login_token`) and `security_loginWithToken` answers; off, that route redirects to the sign-in page. |
| `identifier` | string | `email` | The `User` property an account is identified by (`BaseService::getUserIdentifier()`, the access-token handler, `SecurityController`). |
| `active_delay` | int (seconds) | `60` | `User::isActive()`: active within that many seconds of its last activity (`activeAt`). |
| `online_delay` | int (seconds) | `300` | `User::isOnline()`, the same with this delay. |
| `notifications.expiry` | string (relative date) | `+30 days` | The age past which `user:notifications --clear` erases read notifications (`--expiry` sets another for one run). |
| `register.autoapprove` | bool | `false` | A new account is approved without an administrator (`SecurityController::Register`, and `SecuritySubscriber` for an account still waiting). Off, an account that is not approved is sent to `security_pendingForApproval` until an administrator approves it. |
| `register.notify_admins` | bool | `false` | The administrators (`notifier.admin_role`) are sent an approval request when a verified account registers (`SecurityController::Register`). |

## `access_restriction`

Who may see the site, checked by `Base\Security\Voter\AccessVoter` (see
[Routes and access](routes-and-access.md#accessvoter)).

| Option | Type | Default | Effect |
|---|---|---|---|
| `public_access` | bool | `true` | `ANONYMOUS_ACCESS`: visitors who are not signed in may browse. Off, only signed-in users do (when `user_access` or `admin_access` is on). |
| `user_access` | bool | `true` | `USER_ACCESS`: signed-in users may browse; administrators (`ROLE_ADMIN`) may whenever `admin_access` is on. |
| `admin_access` | bool | `true` | `ADMIN_ACCESS`: administrators may browse; a `ROLE_SUPERADMIN` always may. |
| `firewalls` | list of strings | `[main]` | The firewalls the exceptions below apply to; a request under another firewall is never restricted by them. |
| `route_exceptions` | list of regular expressions | `['/^(?:locale_\|ux_\|user_\|security_)/']` | Route names that no restriction stops and that the referrer never remembers (the sign-in pages, the language switch, the UX endpoints). |
| `exceptions` | list of `{locale, env, host, path, domain, subdomain, scheme}` | `[]` | Addresses let through during a maintenance or before the launch (`EXCEPTION_ACCESS`): the entry for the current locale (then its language, the default locale, its language, then the entry without a locale) is matched; `env` and `locale` are compared, the others are regular expressions on the parts of the address. |
| `redirect_on_deny` | string | `null` | Deprecated, read by nothing: the redirection on a refusal is the setting `base.settings.access_restriction.redirect_on_deny` (back office). |

## `site`

| Option | Type | Default | Effect |
|---|---|---|---|
| `index` | string (route) | `app_index` | The home page's route: `AdvancedRouter::getRouteIndex()`, where redirections without a target go (the demonstration's sign-in, the end of a pending approval). |
| `maintenance.redirect_on_deny` | string (route) | `security_maintenance` | The route a visitor is sent to during a maintenance (`MaintenanceProvider`): the core's maintenance page, `security_maintenance` (`/m`), or a route of the site's. Once the maintenance is over, a visitor on that page is sent to `site.index`. |
| `maintenance.exception` | map of `code` | `[]` | Deprecated, read by nothing. |
| `maintenance.lockpath` | string (path) | `%kernel.project_dir%/public/maintenance.lock` | The lock file whose presence puts the site under maintenance (its first line the downtime, its second the uptime), beside the setting `base.settings.maintenance`. |

## `router`

| Option | Type | Default | Effect |
|---|---|---|---|
| `use_custom` | bool | `null` | `AdvancedRouter`'s own generation and matching (`AdvancedUrlGenerator`, `AdvancedUrlMatcher`: the routes by locale and host); off, routes are generated and matched as Symfony's router does. |
| `ip_access` | bool | `false` | Deprecated, no effect (see the note below). |
| `use_fallbacks` | bool | `false` | Whether `host_fallbacks` is used at all: off, `AdvancedRouter::getFallbackParameters()` gives none and addresses are built from the request's host. |
| `fallback_warning` | bool | `true` | `cache:clear` warns when no host fallback is configured. |
| `host_fallbacks` | list | see the tree | One entry per locale (and environment): `subdomain`, `machine`, `domain`, `port` (lists, the first one is the canonical one), `base_dir`, `reduction` (whether a non-canonical host is reduced to the canonical one). `AdvancedRouter` builds absolute URLs from it and `CookieDomain` the cookies' domain. |

> [!NOTE]
> `RouterSubscriber` applies the IP-address rule only when `isGranted('VALIDATE_IP', $route)`
> is true; no voter of the core decides `VALIDATE_IP` (only `PermissionVoter` could, for a
> user holding that permission tag), so `ip_access` has no effect. It is deprecated rather than
> wired: wired, its default (`false`) would redirect every request that reaches a site by its IP
> address, health checks included.

## `translator`

| Option | Type | Default | Effect |
|---|---|---|---|
| `politeness` | `null`, `plain`, `polite`, `formal` | `null` | The level of address texts are taken in: see [How the site addresses people](../20-architecture/politeness.md). |

## `parameter_bag`

| Option | Type | Default | Effect |
|---|---|---|---|
| `use_setting_bag` | bool | `null` | `SettingBag` reads the settings stored in the database; off, every setting reads as unset. |
| `use_hot_bag` | bool | `null` | `HotParameters` lays the settings over the container's parameters: a setting linked to a parameter (its `bag`, `SettingBag::setBag($path, 'base.user.login_with_token')`) answers for that parameter before the configuration, its value in the default locale (a stored `false`, `0` or `""` included; `null` is no value). `HotParameterBagSubscriber` loads them on each request and command. Off, the parameters are read from the configuration alone. |

## `notifier`

| Option | Type | Default | Effect |
|---|---|---|---|
| `mailer` | bool | `true` | The notifier sends e-mail (when symfony/mailer's `Mailer` exists). |
| `technical_loopback` | bool | `true` | **On by default**: every e-mail goes to the technical recipient instead of its addressee (`Notifier::isTest()` is true for every recipient). Turn it off in production. |
| `technical_recipient.email` | string | `null` | The technical address: the sender of the e-mails, the loopback's addressee, and the error page's support address when the setting `mail` gives none. Unset: `postmaster@<the site's domain>`. |
| `technical_recipient.phone` | string | `null` | The technical recipient's phone number. |
| `admin_role` | string (role) | `ROLE_EDITOR` | Who receives the administrators' notifications (`Notifier::getAdminRecipients()`: the users holding that role). |
| `test_recipients` | list of regular expressions | `['@localhost$']` | With the kernel in debug, an e-mail to an address matching one goes to the technical recipient; `User::isTester()` reads the same list. |
| `options` | list of `{channel, markAsRead}` | `[]` | Handed to the notifier as they are (`BaseNotifier::getOptions()`). |

## `spam`

| Option | Type | Default | Effect |
|---|---|---|---|
| `akismet` | string | `''` | The Akismet key `SpamChecker` uses when the back office's setting `api.spam.akismet` is empty (see [The forms' guard](../20-architecture/guard.md)). |

## `time_machine`

The backups (`Base\Service\TimeMachine`, [Backups](../30-operations/backups.md)).

| Option | Type | Default | Effect |
|---|---|---|---|
| `compression` | string | `gzip` | The compression of the snapshots. |
| `time_limit` | string (relative date) | `'+30 days'` | How long a snapshot is kept. |
| `max_cycle` | int | `-1` | The number of snapshot cycles kept; `-1`: no limit. |
| `snapshot_dir` | string (path) | `null` | Where a snapshot is staged before it is sent; unset: the kernel's cache directory. |
| `excludes` | list of paths | see the tree | Left out of the application's tarball. |

## `icon_provider`

| Option | Type | Default | Effect |
|---|---|---|---|
| `default_adapter` | class name | `Base\Service\Model\IconProvider\Adapter\FontAwesomeAdapter` | The icon set `IconType` (the icon picker) offers by default. |

## `uploader` and `files`

| Option | Type | Default | Effect |
|---|---|---|---|
| `uploader.storage` | string (Flysystem storage) | `null` | The storage an `#[Uploader]` property writes to when it names none. |
| `uploader.formats` | list of `{class, property, width, height}` | `[]` | The sizes `uploader:images` warms up. |
| `uploader.warmup` | bool | `true` | Deprecated, read by nothing. |
| `files.storage` | string | `null` | Deprecated, read by nothing. |

## `images`

Read by `Base\Service\MediaService`.

| Option | Type | Default | Effect |
|---|---|---|---|
| `max_resolution` | class name | `Base\Imagine\Filter\Basic\Definition\UltraHighDefinitionFilter` | The filter that caps an image's definition. |
| `max_quality` | number | `1` | The highest quality an image is encoded at. |
| `timeout` | int (seconds) | `60` | The time limit set while an image is processed. |
| `enable_webp` | bool | `true` | Images served as WebP where the browser takes it. |
| `debug` | bool | `false` | The debug variant of the image routes. |
| `no_image` | list of `{extension, path}` | `[]` | The picture served instead of a missing file, per extension. |
| `fallback` | bool | `false` | Serve `no_image` for a missing file; on, a missing `no_image.<extension>` is an error. |
| `autorotate`, `disable_profiler`, `warmup`, `formats` | | see the tree | Deprecated, read by nothing (the bitmap filters rotate by their own `autorotate` option, true by default). |

## `paginator`

| Option | Type | Default | Effect |
|---|---|---|---|
| `page_range` | int | `1` | Pages shown on each side of the current one. |
| `page_size` | int | `10` | Entries per page. |
| `page_parameter` | string | `page` | The route parameter that carries the page. |
| `default_template` | string | `'@Base/paginator/sliding.html.twig'` | What `paginator()` renders. |

## `obfuscator`

`Base\Service\Obfuscator`: the `data` of the media and UX routes, and the
`|obfuscate` filter.

| Option | Type | Default | Effect |
|---|---|---|---|
| `level` | int | `-1` | The compression level. |
| `uuid` | string (UUID) | `6ba7b811-9dad-11d1-80b4-00c04fd430c8` | The namespace of the shortened identifiers (the URL namespace by default). |
| `encoding` | int | `null` | The compression encoding. |
| `max_length` | int | `0` | The longest string decoded. |
| `compression` | string | `'null'` | The algorithm (`'null'`: none). |
| `cache_dir` | string (path) | `%kernel.cache_dir%` | Where the map of shortened identifiers is kept. It must outlive `cache:clear` and deploys, or the short addresses already given out stop resolving. |

## `twig`

| Option | Type | Default | Effect |
|---|---|---|---|
| `use_custom` | bool | `null` | omnibase's Twig loader (`Base\Twig\Loader\FilesystemLoader`) and its webpack warm-up. |
| `use_form2` | bool | `true` | The form theme's `form2` variant (`FormTypeExtension`). |
| `use_ea` | bool | `true` | The form theme's `easyadmin` variant. |
| `use_bootstrap` | bool | `true` | Bootstrap classes on the form rows (`FormTypeBootstrapExtension`). |
| `autoappend` | bool | `true` | `TwigSubscriber` appends the dependencies a page needs (scripts, stylesheets) to its HTML. |
| `default_path` | string (path) | the package's `templates/` | The `@Base` namespace's directory. |
| `form_themes` | list | `[./form/form_div_layout.html.twig]` | Put before `twig.form_themes` (the parameter `twig.form.resources`). |
| `paths` | list of `{path, namespace}` | `[]` | More directories for the loader, each under its namespace (the main one when none); an entry without a path is an error. |
| `editor.storage` | string (Flysystem storage) | `null` | Where the editor's uploads go (`EditorType`, `EditorController`). |
| `breakpoints` | list of `{name, media}` | `[]` | The stylesheets per media query the webpack renderer adds (`<entry>-<name>.css`). |
| `script_attributes` | `{defer, async, ordered}` | `false, false, true` | The default attributes of the script tags; `ordered` (see the tree). |
| `link_attributes` | `{defer, async}` | `false, false` | The same for the stylesheet links. |
| `themes.modes`, `themes.filters`, `themes.widths`, `themes.audiences`, `themes.events` | maps by name | `[]` | What `Themizer` offers: the colour modes (`class`, `icon`, `prefers`), filters, widths, audiences (`age`, `icon`) and dated events (`begin`, `end`, `priority`, `icon`); the menus `render_theme_modes()`... draw them. |
| `themes.default_audience` | string | `null` | The audience of a visitor whose age is unknown. |

## `attributes`

| Option | Type | Default | Effect |
|---|---|---|---|
| `use_custom` | bool | `null` | omnibase's attribute reader (the `#[Uploader]`, `#[Vault]`... of the entities). |
| `paths` | list of paths | `['%kernel.project_dir%/src/Attribute']` | Where the reader looks for the application's attribute classes. |

## `security`

| Option | Type | Default | Effect |
|---|---|---|---|
| `two_factor.required_roles` | list of roles | `[]` | Roles whose holders must have a second factor: see [Account security](../20-architecture/account-security.md). |
| `two_factor.postpone` | bool | `true` | Whether the enrolment prompt offers "not now". |

## `demo`, `opening_hours`, `local_business`, `guard`, `signatures`, `comments`, `vault`, `download_links`

Each has its page, which describes its options:
[The demo environment](../20-architecture/demo.md),
[Opening hours](../40-commons/opening-hours.md),
[LocalBusiness JSON-LD](../40-commons/local-business.md),
[The forms' guard](../20-architecture/guard.md),
[Signatures](../40-commons/signatures.md),
[Comments](../40-commons/comments.md),
[Sealed fields](../20-architecture/vault.md),
[Downloads and QR codes](../40-commons/downloads-and-qr.md). Their types and
defaults are in the tree above; the integer options of `guard` and `comments`
refuse a negative value, `download_links.ttl` one below 1.

## `extension`

| Option | Type | Default | Effect |
|---|---|---|---|
| `max_revisions` | int | `5` | Revisions kept per versioned entity (`VersionableSubscriber`). |
| `empty_trash` | string (relative date, or a number of days) | `'+7 days'` | How long a trashed entity is kept before `trash:purge` may destroy it (`TrasheableSubscriber`, `TrashManager`). |
| `logging_default_expiry` | int | `180` | Deprecated, read by nothing. |

## `screens`

| Option | Type | Default | Effect |
|---|---|---|---|
| `version` | string | `null` | The front end's version a screen expects: `ScreenResponder` compares it with the request's `X-Omnibridge-Version`, and a front end of another version reloads the page whole. |

## What the extension registers

Besides the parameters, `BaseExtension`:

- loads the package's `config/services.php` (every file of `config/services/`),
  `services-public.php`, `services-fix.php` and `services-decoration.php`;
- puts `twig.form_themes` after `base.twig.form_themes` in `twig.form.resources`;
- before every extension loads (`prepend()`): sets `base.package_dir`, the
  package's directory wherever Composer installed it (what a configuration names
  instead of a vendor path: `'%base.package_dir%/docs'`); declares to DoctrineBundle
  the DBAL types `utc_datetime_immutable`, `utc_datetime`, the passkey's columns
  and every type of `src/Enum` (the package's and the application's) that has a
  `getStaticName()`; and, when symfony/ux-twig-component is installed, the
  component namespaces `Base\Twig\Component\` (templates `@Base/components/`,
  prefix `base`) and `App\Twig\Component\` (`@App/components/`, prefix `app`);
- tags by autoconfiguration what implements:

| Interface or class | Tag |
|---|---|
| `Base\Service\Model\IconProvider\AbstractIconAdapter` | `base.service.icon` |
| `Base\Service\Model\IconProvider\IconAdapterInterface` | `base.icon_provider` |
| `Base\Database\Entity\EntityExtensionInterface` | `base.entity_extension` |
| `Base\Attributes\AttributeInterface` | `base.attribute` |
| `Base\Service\Model\Sharing\SharingAdapterInterface` | `base.service.sharing` |
| `Base\Cache\Abstract\AbstractLocalCacheInterface` | `base.simple_cache` |
| `Base\Service\Model\Currency\CurrencyApiInterface` | `currency.api` |
| `Base\Service\Model\Obfuscator\CompressionInterface` | `obfuscator.compressor` |
| `Base\Twig\Renderer\TagRendererInterface` | `twig.tag_renderer` |
| `Symfony\Component\Workflow\WorkflowInterface` | `workflow` |
| `Base\Demo\DemoAccountProviderInterface` | `base.demo_account_provider` |
| `Base\Database\Repository\ServiceEntityRepository` | `doctrine.repository_service` |
| `Base\EntityDispatcher\EventDispatcherInterface` | `doctrine.event_listener` on `prePersist`, `postPersist`, `preUpdate`, `postUpdate`, `preRemove`, `postRemove`, `postFlush`; `base.entity_dispatcher`; `kernel.reset` (method `reset`) |
