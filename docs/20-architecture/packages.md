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

### Two bundles required de facto

Two of the suggested bundles cannot be left out of a Symfony application, as long as no seam
separates them from the core:

- **scheb/2fa** - `Base\Entity\User` implements its `Scheb\TwoFactorBundle\Model\*`
  interfaces (TOTP, e-mail code, backup codes, trusted devices). They live in the bundle's
  package; without it the user class does not load.
- **league/flysystem-bundle** - `Base\Service\Flysystem` (the `flysystem` service, which
  the file, image, upload, backup and cache-clear services read) extends its `LazyFactory`
  and is built from its storages (`local.cache` at least).

The others have a fallback:

| Bundle | Without it |
|---|---|
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
