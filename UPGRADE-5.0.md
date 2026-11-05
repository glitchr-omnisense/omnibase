# Upgrading from 4.x to 5.0

5.0 is the plan "omnibase agnostic"'s step F6: omnisense/omnibase becomes a library that imposes
no framework, no persistence and no view engine. Branch `5.x` (`5.0.x-dev`); `4.x` stays as it was
for the applications still locked on it - their `"omnisense/omnibase": "4.x-dev as 4.0.x-dev"` never
reaches 5.0, and the bridges' 1.x serve both.

## Composer

- `type` is `library` (it was `symfony-bundle`): the bundle is omnibridge/symfony's (below).
- `require` is the Foundation's (docs/20-architecture/packages.md): PHP and its extensions, the PSR
  interfaces, omnisense/omnibridge, omnisense/omnidrive, omnibridge/composer, doctrine/collections,
  the libraries the Foundation calls (embed, endroid/qr-code, svg-sanitize, hashids, imagine,
  crawler-detect, league/flysystem) and the standalone Symfony components kept (string, uid, intl,
  translation, mime, mailer, notifier, property-access, options-resolver, yaml, password-hasher,
  filesystem, finder, serializer) with the contracts (service, translation, event-dispatcher, cache,
  http-client).
- No longer required, each now brought by the bridge that needs it or suggested:

| Package | 5.0 |
|---|---|
| doctrine/orm, dbal, persistence, event-manager, common, beberlei/doctrineextensions, scienta/doctrine-json-functions, omnibridge/doctrine | omnibridge/doctrine (which requires beberlei and scienta since this release); the core suggests omnibridge/doctrine |
| symfony/validator, cache, event-dispatcher, process, workflow, var-dumper, error-handler, asset, expression-language | omnibridge/symfony |
| symfony/http-client, discord-notifier, league/flysystem-ftp, phpoffice/phpspreadsheet, web-auth/webauthn-lib, florianv/swap | suggested: an application that uses the feature requires the package |
| twig/twig and its extras | suggested since 4.x (step F4) |

An application that used one of the suggested packages without requiring it requires it now:
`composer why-not` or a `composer update` that removes it tells which.

## A Symfony application

1. `composer.json`: `"omnisense/omnibase": "5.x-dev as 5.0.x-dev"`, beside `omnibridge/symfony`,
   `omnibridge/doctrine` and `omnibridge/twig` (`1.x-dev`), which bring the framework, the
   persistence and Twig's helpers.
2. `config/bundles.php`:

   ```php
   - Base\BaseBundle::class => ['all' => true],
   + Omnibridge\Symfony\Omnibase\BaseBundle::class => ['all' => true],
   ```

3. `config/routes.yaml`, the import of the core's controllers:

   ```yaml
   base_controller:
   -    resource: "@BaseBundle/Controller"
   -    type: attribute
   +    resource: .
   +    type: omnibase
       prefix: /
   ```

   omnibridge/symfony's loader imports its controllers (`Omnibridge\Symfony\Omnibase\Controller\*`)
   and the core's actions (`src/Controller/Action`, `SignatureController`). The routes keep their
   names, paths and requirements; their `_controller` names the moved class.
4. Empty the caches (`var/cache/*`, the cache pools): the container, the routes and the core's
   pool name the moved classes.

Nothing else changes. The bundle keeps its name (`BaseBundle`, `@BaseBundle/...` is still the
core's `src/`), its configuration (`base:`), its templates (`@Base/...`) and translations, and
its entities' mapping (now declared by its extension, under the same name).

## What moved to omnibridge/symfony

Each class keeps its path under `Omnibridge\Symfony\Omnibase\`, and its former name is an alias
the core declares (src/Resources/former-names.symfony.php), resolved where omnibridge/symfony is
installed, until 6.0:

- `Base\BaseBundle`, `Base\Bundle\{AbstractBaseBundle, AbstractBaseExtension, AbstractBaseConfiguration}`;
- `Base\DependencyInjection\*`: `BaseExtension`, `BaseConfiguration`, the compiler passes, the dumpers;
- the Symfony controllers, `Base\Controller\*` but `Base\Controller\Action\*` and
  `SignatureController`, which stay (shared with omnibridge/laravel);
- the service configuration, `config/services*.php` and `config/services/*.php`, now
  omnibridge/symfony's `config/omnibase/`.

The controllers' services are registered under their new names; their former ids are aliases
(`framework.error_controller: Base\Controller\ErrorController::Main` keeps working). A bundle
extending `Base\Bundle\AbstractBaseBundle` keeps working unchanged.

## The entities reach no service (step E1)

- `Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity` on an application's entity keeps working
  under Symfony; `Base\Validator\Constraints\Unique` is the same rule on any persistence (the core's
  `Redirection` and `TextOverride` use it). It takes `fields`, `message`, `entityClass`, `repositoryMethod`,
  `errorPath`, `ignoreNull`, `groups`, `payload`; not `em`, `service` nor `identifierFieldNames`.
- A `__toLink()` override typed on `UrlGeneratorInterface::ABSOLUTE_PATH` keeps working (the value is the
  same, 1); `Base\Contract\Link::ABSOLUTE_PATH` names it without the routing component, and
  `Base\Service\Links` makes the address on the `Urls` contract.
- An entity of the application that calls BaseTrait's accessors keeps working (BaseTrait is not
  deprecated, FrameworkAccessorsTrait's part is); the core's entities ask `Base\Contract\Runtime`
  (`notifier()`, `translator()`, `projectDir()` are new).

## Stays in the core, deliberately

- The symfony/process patch (`extra.omnibridge.patches`, `process-timeout-from-env`): it applies only
  where symfony/process is installed (skipped otherwise). It moves to omnibridge/symfony when 4.x
  is no longer served: declared by both, the 4.x core's declaration would fail on the sites still on
  4.x (omnibridge/composer applies the bridge's first).
- The former names (`Base\...` aliases): removed at 6.0, after step S of the plan.
