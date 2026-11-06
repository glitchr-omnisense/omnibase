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

## The access rules are the core's (step F5e, slice 1)

- `Base\Security\Voter\{AccessVoter, AdminVoter, PermissionVoter, ComplaintVoter}` keep their names, constants,
  constructors and decisions; they ask `Base\Auth\Policy\*`. A voter of the application that extended one keeps
  working; its rule can be asked of the policy instead (`new Base\Auth\Policy\Permission()`...).
- `Base\Subscriber\SecuritySubscriber` keeps its events and priorities; its access restriction and its word of
  welcome are `Base\Auth\Policy\Restriction` and `Welcome`.

## The request rules are the core's (step F5e, slice 2)

- `Base\Subscriber\{SecuritySubscriber, LocalizerSubscriber, IntegritySubscriber, AnalyticsSubscriber}` keep their
  names, events, priorities and services; their rules are `Base\Request\Rule\*` (`Accounts`, `Locale`, `Integrity`,
  `Analytics`, `MaintenancePage`, `LaunchPage`). SecuritySubscriber's service takes two more arguments,
  `Omnibridge\Persistence\UnitOfWork` and `Omnibridge\Contract\Urls` (both optional).
- `LocalizerSubscriber::choose()` is `Base\Request\Rule\Locale::choose()`.
- `MaintenanceProvider::redirectOnDeny()` and `Launcher::redirectOnDeny()` keep working and are deprecated until
  6.0: ask `Base\Request\Rule\MaintenancePage` and `LaunchPage`.
- `Base\Service\Redirections::responseFor()` keeps its answer; its rule is `Base\Request\Rule\Redirection`.

## The console's logic is the core's (step F5e, slice 3)

- `Base\Console\Command\{TranslationCrud, TranslationEntities, TranslationEnums, TranslationControllers,
  TranslationSettings, CacheClear, CacheClearSessions, DemoReset, UploaderEntities, UploaderImages,
  UploaderImagesCrop}Command` keep their names, options, constructors and services; their logic is
  `Base\Console\Task\*`, run with an input object and printing a `Base\Console\Report`. A command of the application
  that extended one keeps working; a script can run the task itself (`(new TranslationEntities(...))->run(new
  TranslationEntitiesInput())->text()`).
- `Base\Traits\CacheClearTrait` is deprecated (its checks are `Base\Console\Task\CacheClear`'s); removed at 6.0.
- Under Laravel (omnibridge/laravel), the same commands are artisan's; cache:clear's is `omnibase:cache:clear`.
- Slice 3b: the other commands as well - `AutocompleteEntities`, `BaseMapping`, `EntityDiscriminator`, `IconEntities`,
  `IconEnums`, `MailTrap` (task `MailerTrap`), `Notifier` (task `NotifierOverview`), `NotifierMailTest`,
  `ThreadPublishable`, `TimeMachineSnapshot{,Backup,Restore}` (task `TimeMachineSnapshot`), `TimeMachineStorage`,
  `TrashPurge`, `UserCreate`, `UserNotification`; the tasks of `DoctrineArrayUpgrade`, `DoctrineDatabaseImport` and
  `DoctrineSchemaCharset` are omnibridge/doctrine's (`Omnibridge\Doctrine\Omnibase\Console\Task\`).
- `Base\Service\TimeMachineInterface` declares `getDatabaseList()` and `getStorageList()`: an implementation of
  one's own adds them.
- Under Laravel, artisan has 11 of them (base:mapping, icon:enums, icon:entities, autocomplete:entities,
  entity:discriminator, app:notifier, notifier:mail:test, user:notifications, thread:publishable, trash:purge,
  user:create); base:mailer:trap, timemachine:* and doctrine:* stay Symfony's.

## The services are the core's declaration (step F5e, slice 4a)

- `Base\Services::definitions()` lists every service of the core (192), not only the 19 of step F5a; its definitions
  are grouped (`Services::group()`), and a definition may give its class through a `parent`, its arguments by position,
  `requires`, `environment`, `tags`, `calls`, `decorates`, `bind` and a `symfony` part. Code that read the list's
  `class` and `contracts` as plain values reads them through `Services::classOf()` and `Services::contracts()` (a contract
  may now carry its options: `'alias' => ['public' => true, 'target' => ...]`).
- Under Symfony nothing changes: the same services, ids, arguments, tags and aliases (omnibridge/symfony compiles the
  declaration). Under Laravel, omnibridge/laravel binds every declared service that applies and can be built there
  (lesringostars-laravel: 142 of 192); an application's own binding still wins. In plain PHP, `Omnibridge\Container`
  builds them all from the declaration (tests/Services/graph-without-framework.php).

## The options are the core's declaration (step F5e, slice 4b)

- The schema of `base:` is `Base\Options` (data in `src/Resources/options.php`), no longer omnibridge/symfony's
  hand-written tree. A Symfony site changes nothing: the same options, defaults, deprecations and `base.*` parameters
  (`config:dump-reference base` is the same).
- A Laravel site's `config/base.php` is validated when omnibridge/laravel boots: an option the schema does not know, or
  of the wrong type, now stops the application with the message Symfony gives it (`Unrecognized option "x" under
  "base"...`), where it was silently ignored. Every option the file leaves out takes the core's default, so
  `config('base.x')` and `Runtime::parameters()` answer for each one; `%kernel.project_dir%` is the application's base
  path, `%kernel.cache_dir%` omnibridge's cache folder (`omnibridge.cache_dir`, else `storage/framework/cache/omnibase`):
  the obfuscator's identifier map, when `base.obfuscator.cache_dir` is not set, is kept there (it was
  `storage/framework/cache/omnibase-obfuscator`).
- An unset `BASE_TRADING_LIVE` means live under Laravel too, as under Symfony.

## What is discovered is the core's declaration (step F5e, slice 4c)

- The lists omnibridge/symfony's compiler passes held (the tags of the core's interfaces, the services each collecting
  service is handed, the contracts, the admin's links, the guards' arguments, the application's texts, the local
  storages, the enumerations, the repository interfaces) are `Base\Discovery`'s registries (data in
  `src/Resources/discovery.php`), compiled by one pass, `Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\DiscoveryPass`.
  A Symfony site changes nothing: its compiled container is the same.
- The fifteen former passes are `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\*` (registered
  for a 4.x core only); their former names (`Base\DependencyInjection\Compiler\Pass\*`) point there. Code that read
  `ContractsPass::SERVICE`/`CONTRACTS` reads `Base\Discovery::registry('contracts')`; `ApplicationTextsPass::read()` is
  `Base\Translation\ApplicationTexts::read()`; `FlysystemStoragesPass::STORAGES` is `registry('storages')['local']`.
- `Base\Services` no longer lists the obfuscator's compressions as its argument: they are the `collect` registry's
  (the services tagged `obfuscator.compression`), which every host applies. A plain-PHP host passes
  `Base\Discovery::registries()` to `Omnibridge\Container::fromDefinitions()` (fourth argument), or its obfuscator has no
  compression.
- omnibridge/laravel binds the entity dispatchers and ThreadSubscriber from the declaration and registers every Doctrine
  listener the declaration tags on the events its registries give (an application's `omnibridge.omnibase.entity_dispatchers`
  on the events of `Base\EntityDispatcher\EventDispatcherInterface`); `OmnibaseServices::DISPATCHERS` is deprecated.

## Stays in the core, deliberately

- The symfony/process patch (`extra.omnibridge.patches`, `process-timeout-from-env`): it applies only
  where symfony/process is installed (skipped otherwise). It moves to omnibridge/symfony when 4.x
  is no longer served: declared by both, the 4.x core's declaration would fail on the sites still on
  4.x (omnibridge/composer applies the bridge's first).
- The former names (`Base\...` aliases): removed at 6.0, after step S of the plan.
