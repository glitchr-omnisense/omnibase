---
title: What the core requires
order: 21
---

# What the core requires (4.0)

Since 4.0 (branch `4.x`, plan "omnibase agnostique", step C7) `omniscient/omnibase`
requires its **Fondation** alone: what `tests/Fondation.php` loads, and what it stands on.
The framework is no longer a requirement of the core: a Symfony application adds it,
a Laravel application does not.

| | What | Where it comes from |
|---|---|---|
| **The Fondation** | PHP 8.2 and its extensions (exif, igbinary, imagick); Doctrine (orm, dbal, collections, persistence, event-manager, common) and its DQL functions; Twig and its extras (cache, cssinliner, inky, intl); the standalone Symfony components (asset, cache, error-handler, event-dispatcher, expression-language, filesystem, finder, http-client, intl, mailer, mime, notifier, options-resolver, password-hasher, process, property-access, serializer, string, translation, uid, validator, var-dumper, workflow, yaml, the contracts); the PSRs (cache, clock, container, event-dispatcher, log, simple-cache); league/flysystem; web-auth/webauthn-lib (`Base\Entity\User\Passkey`); omnibridge/doctrine and omnibridge/twig; the libraries (imagine, embed, qr-code, spreadsheets, barcodes...) | `require` of the core |
| **The framework** | FrameworkBundle, DoctrineBundle, SecurityBundle (security-core, -http, -csrf), TwigBundle and twig-bridge, MonologBundle, twig/extra-bundle, Config, DependencyInjection, HttpKernel, HttpFoundation, Routing, Form, Console, Messenger, Dotenv, doctrine-bridge | **omnibridge/symfony**, which a Symfony application requires beside the core |
| **The bundles the core works with** | scheb/2fa, league/flysystem-bundle, hwi/oauth-bundle, web-auth/webauthn-symfony-bundle, api-platform/symfony and doctrine-orm, symfony/webpack-encore-bundle, symfony/ux-twig-component, glitchr/ux-google, glitchr/well-known, nelmio/cors-bundle, nelmio/security-bundle | `suggest` of the core; the application requires the ones its `config/bundles.php` registers |

`symfony/console` is still installed with the core alone: doctrine/orm 3 requires it. Nothing
of the Fondation loads it, which is what `bare` checks: "free of Symfony" reads *nothing of
the framework loaded*, not *absent from vendor*.

## A Symfony application

```bash
composer require omniscient/omnibase:4.0.*-dev omnibridge/symfony:1.x-dev
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

### None required de facto

Up to step C7b two of them could not be left out; each has its fallback now, and the user
class and the storages load without either:

- **scheb/2fa** - `Base\Entity\User` implements `Base\Contract\SecurityUser` and
  `Base\Contract\TwoFactorUser`. Where symfony/security-core and scheb/2fa are installed,
  they are aliases of `Base\Bridge\Symfony\SecurityUser` (Security's `UserInterface` and
  `PasswordAuthenticatedUserInterface`) and `Base\Bridge\Symfony\TwoFactorUser` (scheb's
  TOTP, e-mail, backup-code and trusted-device interfaces): under Symfony the user implements
  exactly what it did. Elsewhere they are empty interfaces, and the user loads without them.
  A host that holds those packages but runs without the framework (a Laravel application
  reading a Symfony application's `vendor/`) says so with `OMNIBASE_FRAMEWORK=none` or
  `Base\Contract\Framework::without()` before the user is loaded. Without the bundle
  registered (the package installed) the interfaces are still declared: only the two-factor
  sign-in itself is the bundle's.
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
| web-auth/webauthn-symfony-bundle | `PasskeyRepository` and `WebauthnUserEntityRepository` are not registered (their interfaces are the bundle's); no passkeys |
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
