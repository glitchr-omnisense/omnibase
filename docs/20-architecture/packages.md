---
title: What the core requires
order: 21
---

# What the core requires (4.0)

Since 4.0 (branch `4.x`, plan "omnibase agnostique", step C7) `omnisense/omnibase`
requires its **Foundation** alone: what `tests/Foundation.php` loads, and what it stands on.
The framework is no longer a requirement of the core: a Symfony application adds it,
a Laravel application does not.

| | What | Where it comes from |
|---|---|---|
| **The Foundation** | PHP 8.4 and its extensions (exif, igbinary, imagick); Doctrine (orm, dbal, collections, persistence, event-manager, common) and its DQL functions; Twig and its extras (cache, cssinliner, inky, intl); the standalone Symfony components (asset, cache, error-handler, event-dispatcher, expression-language, filesystem, finder, http-client, intl, mailer, mime, notifier, options-resolver, password-hasher, process, property-access, serializer, string, translation, uid, validator, var-dumper, workflow, yaml, the contracts); the PSRs (cache, clock, container, event-dispatcher, log, simple-cache); league/flysystem; web-auth/webauthn-lib (`Base\Entity\User\Passkey`); omnibridge/doctrine and omnibridge/twig; the libraries (imagine, embed, qr-code, spreadsheets, barcodes...) | `require` of the core |
| **The framework** | FrameworkBundle, DoctrineBundle, SecurityBundle (security-core, -http, -csrf), TwigBundle and twig-bridge, MonologBundle, twig/extra-bundle, Config, DependencyInjection, HttpKernel, HttpFoundation, Routing, Form, Console, Messenger, Dotenv, doctrine-bridge | **omnibridge/symfony**, which a Symfony application requires beside the core |
| **The bundles the core works with** | scheb/2fa, league/flysystem-bundle, hwi/oauth-bundle, web-auth/webauthn-symfony-bundle, api-platform/symfony and doctrine-orm, symfony/webpack-encore-bundle, symfony/ux-twig-component, glitchr/ux-google, glitchr/well-known, nelmio/cors-bundle, nelmio/security-bundle | `suggest` of the core; the application requires the ones its `config/bundles.php` registers |

`symfony/console` is still installed with the core alone: doctrine/orm 3 requires it. Nothing
of the Foundation loads it, which is what `bare` checks: "free of Symfony" reads *nothing of
the framework loaded*, not *absent from vendor*.

**Doctrine: the latest major, one line.** The core follows each Doctrine package's current
major, open upwards - `doctrine/orm` `^3.4`, `doctrine/dbal` `^4.2` (omnibridge/doctrine's
floors), `doctrine/collections` `^3.1` (which requires PHP 8.4, the core's floor), `doctrine/event-manager` `^2.0`,
`doctrine/persistence` `^4.0`, `doctrine/common` `^3.0` - with no older branch kept alive, no
upper bound and no pinned version; Doctrine is used as published (no patch, no fork).
doctrine/dbal included (4.0, step C6b): no Composer plugin rewrites its schema managers; what
omnibase needs of them goes through DBAL's own `SchemaManagerFactory`
(`Base\Bridge\Doctrine\SchemaManagerFactory`, a SET column read with its values, the
`(DC2Type:...)` comments of before left out). A new
major is taken up when it comes, not maintained beside the old one.

## A Symfony application

```bash
composer require omnisense/omnibase:4.0.*-dev omnibridge/symfony:1.x-dev
```

then the bundles its `config/bundles.php` registers, for an application made on omnibase's
usual set:

```bash
composer require scheb/2fa league/flysystem-bundle hwi/oauth-bundle web-auth/webauthn-symfony-bundle \
    api-platform/symfony api-platform/doctrine-orm symfony/webpack-encore-bundle symfony/ux-twig-component \
    glitchr/ux-google glitchr/well-known nelmio/cors-bundle nelmio/security-bundle
```

An application coming from 3.x already had every one of them, through the core: requiring
them itself keeps them (Composer would otherwise remove what nothing requires any more). No
Flex recipe runs again for a package already locked: nothing changes in `config/`.

### Composer: omnibridge/composer

The core requires one Composer plugin, **omnibridge/composer** (in place of
glitchr/base-plugin until 4.0), and declares what it asks of it in its own
`composer.json`, `extra.omnibridge`:

- **the stubs** of `App\Entity`, `App\Enum`, `App\Form`, `App\Notifier` and
  `App\Repository` - one per plain class `Base\<Name>\X` the application does not
  define in `src/<Name>/`, the alias `AbstractBaseBundle` declares at run time - by
  the providers `Base\Bridge\Composer\Stub\*`, written after every
  `dump-autoload` into `vendor/composer/omnibridge/stubs/`, which no autoloader
  reads (for an IDE; never a definition at run time; `StubsTest`). The providers
  are loaded by the engine alone, outside the Foundation;
- **one patch**, `process-timeout-from-env` on symfony/process: a `Process` built
  without a timeout waits `SYMFONY_PROCESS_TIMEOUT` seconds, 60 when unset.

An application allows the plugin:

```json
"config": { "allow-plugins": { "omnibridge/composer": true } }
```

and checks vendor/ in its CI with `composer omnibridge:patches --check` (and
`composer omnibridge:stubs --check`). `composer omnibridge:patches --diff` shows
the patch.

Nothing else is changed in an application: its `bin/console` keeps its own line,
`use Base\Console\Application;` in place of FrameworkBundle's (glitchr/base-plugin
used to rewrite it at each install; a site made from the skeleton writes it once).

### None required de facto

Up to step C7b two of them could not be left out; each has its fallback now, and the user
class and the storages load without either:

- **scheb/2fa** - `Base\Entity\User` implements `Base\Contract\SecurityUser` and
  `Base\Contract\TwoFactorUser`. Where symfony/security-core and scheb/2fa are installed,
  they are aliases of `Base\Bridge\Symfony\SecurityUser` (Security's `UserInterface` and
  `PasswordAuthenticatedUserInterface`) and `Base\Bridge\Symfony\TwoFactorUser` (scheb's
  TOTP, e-mail, backup-code and trusted-device interfaces): under Symfony the user implements
  exactly what it did. Elsewhere they are empty interfaces, and the user loads without them.
  Installed means declared, and nothing else decides (`Base\Contract\Framework::declares()`,
  step C7c): no environment variable, no switch - a Laravel application reading a Symfony
  application's `vendor/` gets the user with Security's interfaces, which ask nothing of it
  but the methods the user has. Without the bundle registered (the package installed) the
  interfaces are still declared: only the two-factor sign-in itself is the bundle's. Where
  the framework is not installed is measured on such a vendor: `bare` loads the Foundation
  with the core installed alone (`composer install --no-dev`, see the harness). The users'
  repository follows the same rule (step R2): `Base\Repository\UserRepository` implements
  `Base\Contract\SecurityUserLoader` and `SecurityUserProvider`, aliases of
  `Base\Bridge\Symfony\SecurityUserLoader` (doctrine-bridge's `UserLoaderInterface`) and
  `SecurityUserProvider` (Security's `UserProviderInterface` and `PasswordUpgraderInterface`)
  where symfony/doctrine-bridge and symfony/security-core are installed, empty interfaces
  elsewhere. So do the passkeys' repositories (plan "omniauth", AU1): `PasskeyRepository` and
  `WebauthnUserEntityRepository` implement `Base\Contract\PasskeyRecords` and `PasskeyUsers`,
  WebauthnBundle's interfaces where it is installed.
- **league/flysystem-bundle** - `Base\Service\Flysystem` (the `flysystem` service, which the
  file, image, upload, backup and cache-clear services read) is the core's own class, built from
  a PSR-11 container of storages by name: the services tagged `flysystem.storage`, which the
  bundle tags from the application's `config/packages/flysystem.yaml`. Without the bundle,
  `FlysystemStoragesPass` gives omnibase's local storages: `local.cache`
  (`var/cache/<env>/images`), `local.uploads` and `local.wysiwyg` (`var/storage/...`), each
  with its `.public` under `public/`.

The others have a fallback:

| Bundle | Without it |
|---|---|
| scheb/2fa | the user still declares its interfaces when the package is installed; no two-factor sign-in |
| league/flysystem-bundle | the `flysystem` service on omnibase's local storages (above) |
| hwi/oauth-bundle | the service `Base\Security\UserProvider` is a `UserProvider` (with it, an `OAuthUserProvider`: the same, declared OAuth-aware); `oauth_available()` false |
| web-auth/webauthn-symfony-bundle | `PasskeyRepository` and `WebauthnUserEntityRepository` declare nothing of it (`Base\Contract\PasskeyRecords` and `PasskeyUsers` are empty interfaces) and nobody asks them; the passkeys' table is the core's (`Passkey` and its three column types); no passkey ceremony |
| api-platform/symfony | the decoration of its resource name collection is dropped; `#[ApiResource]` is an attribute nothing reads |
| symfony/webpack-encore-bundle | `encore_entry_*_tags` (the core's own functions) print the core's build alone; the warmer reads no application build |
| symfony/ux-twig-component | the `twig_component` defaults are not prepended |
| glitchr/ux-google | `AnalyticsSubscriber` gets no `ga.service`; no captcha badge, no reCAPTCHA field |
| glitchr/well-known, nelmio/cors-bundle, nelmio/security-bundle | nothing of the core names them |

## A Laravel application

Laravel does not install Symfony's framework: it requires the core and, when it exists,
omnibridge/laravel, which fills the contracts (`Base\Contract\Runtime`, see
[The contracts](contracts.md)) with Laravel's services. Doctrine stays the storage
(`laravel-doctrine/orm`), Twig the view (omnibridge/twig). The pilot,
`lesringostars-laravel`, does so already from a Symfony application's `vendor/`.

## The core's own suite

`require-dev` holds omnibridge/symfony and the bundles the core's code names (scheb/2fa,
league/flysystem-bundle, hwi/oauth-bundle, web-auth/webauthn-symfony-bundle,
api-platform/symfony, symfony/webpack-encore-bundle, glitchr/ux-google), so that
`Dockerfile.test` (`composer install` of the core alone) resolves what its kernel tests use.
The harness's application (`docker/Dockerfile`) requires omnibridge/symfony and every bundle
its `config/bundles.php` registers, as a site does.
