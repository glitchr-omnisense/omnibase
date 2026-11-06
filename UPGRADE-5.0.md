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

## The account's pages are the core's (step F5e, slice 5a)

- `Base\Controller\{SecurityController, LocalizerController, RescueController, DemoController}` and
  `Base\Controller\Client\UserProfileController` (omnibridge/symfony's since F6) keep their routes, names, paths and
  answers; their decisions are `Base\Auth\Account\*` - `SignIn`, `SignOutPage`, `AlreadySignedIn`, `Verification`,
  `Closing`, `Passwords`, `WaitingPages`, `ProfileAccess`, `RescuePage`, each answering a `Base\Auth\Account\Outcome`
  -, `Base\Demo\DemoSignIn` and `Base\Request\LocaleSwitch`, declared services (`Base\Services`).
- Their constructors changed: they take the rules. A configuration that wired one by hand (rare: the bridge
  registers them) gives the new arguments; a site that extended one reads its rule instead of its former
  properties (`$this->notifier`, `$this->tokenRepository`, `$this->userRepository` are gone from SecurityController).
- `Base\Auth\Account\Outcome` is what each rule decides (a route, an address, the index, the page the visitor was
  going to, a page, an account to sign in first, a refusal); omnibridge/symfony's
  `Omnibridge\Symfony\Omnibase\Response\AnswersOutcomes` answers it in a controller, as the controllers did.
- Fixed on the way: the sign-in link's page redirected with `redirectToRoute()` to an address (a
  RouteNotFoundException for every valid link and every address posted where omnibase issues the links); the
  e-mail's verification link answered 500 to a signed-in, unverified account when the token was unknown;
  `/account-goodbye` answered 500 to a visitor (now 403).

## The other pages are the core's too (step F5e, slice 5b)

- omnibridge/symfony's UserSettingsController, NotificationController, Api\ThreadController, ThreadSearchController,
  UserSearchController, ContactController, ProfilerController, ErrorController, AutovalidateController,
  AutocompleteController, MediaController, WidgetController, ShortLinkController, SitemapController, MainController
  and LocalizerController::Timezone() keep their routes, names and paths; their decisions are the core's
  (`Base\Auth\Account\{SecondFactor, Devices, MemberSearch}`, `Base\Thread\{ThreadActions, ThreadSearch}`,
  `Base\Notifier\{NotificationCenter, ContactMessage, TestRecipient}`, `Base\Request\{TimezoneSwitch, ErrorPage,
  ShortLinkRedirect, AttachmentDownload, SitemapPage}`, `Base\Screen\Welcome`, `Base\Field\Validation\Autovalidate`,
  `Base\Field\Search\ExchangeRate`), declared services.
- Their constructors take the rules. ThreadSearchController keeps its three former arguments (an application's
  controller may extend it, as lesringostars' does) and takes the rule fourth. A site that extended another one reads
  the rule instead of its former properties (NotificationController's `$notificationRepository`, `$webPush`,
  `$csrfTokenManager`, its `serialize()` - the bell's item is `Base\Controller\Action\Notification\Latest::item()`;
  MediaController's `$mediaService`, `$fileService`, `$flysystem`, `$imageCropRepository`, `$localCache` and its
  `redirectToRoute()` override - ImageRoute's).
- `/timezone/{_timezone}` takes a zone with its slash and answers as `/locale/{_locale}` does (see the CHANGELOG): a
  link that relied on it going to the index whatever it carried now changes the zone.

## The repositories' queries in the finder language (step E2)

- omnibridge/doctrine's repositories answer the same methods with the same rows; most of their hand-written queries
  are finders now (see the CHANGELOG). Nothing to change in an application that calls them.
- Removed: `ImageRepository` and `ImageCropRepository`'s `findByExampleField()` and `findOneBySomeField()`, maker
  boilerplate querying a field that does not exist (they could only fail). The names now reach the finder, which
  refuses an unknown field as well.
- An application's repository that extended one of the core's and overrode `NotificationRepository::visibleFor()`
  should know that `countUnreadFor()` and `findVisibleFor()` no longer call it under 5.0 (their criteria are
  `visibleTo()`'s, a finder group); it stays the 4.x query.
- New: `Base\Database\Repository\Finder\Group` (parentheses in a finder), the aggregates `sumOf`/`minOf`/`maxOf`,
  and `#[Base\Database\Repository\BackendOnly]` with `UnsupportedQueryException` for the methods one persistence runs
  (the analytics' native SQL, `Redirections::hit()`).

## An association by its key; the repositories' interfaces (steps E2b, E2')

- Nothing to change for a caller: `Comments::findVisible()`/`countVisible()` give the rows they gave.
- New: `Base\Database\Repository\Finder\Key` (a to-one association compared by its foreign key, no join) and
  `Base\Repository\User\Complaints`. `Base\Repository\Thread\Comments` gains `findVisible()`, `countVisible()`,
  `countPending()` and `findPending()`: a class of an application implementing `Comments` itself (none known) adds them.
- Type on the interfaces, not on the repositories' classes (`Base\Repository\...Repository`, former names removed at 6.0):
  `Settings`, `Hours\{ScopedWeeks, SpecialDays, WeekDays}`, `Thread\Comments`, `Analytics\Visits`... exist in 4.x as well;
  `User\Complaints` in 5.0 only.

## The schema created from the description (step F7a)

- Nothing to change: `omnibridge:schema:create` is new (`bin/console`, `php artisan`, or
  `vendor/bin/omnibridge-schema-create --schema=vendor/omnisense/omnibase/src/Resources/schema.php` in plain PHP). It
  creates the tables of a new site in an empty database and refuses one that has them; an existing database keeps its
  migrations.
- An application whose code reads `Omnibridge\Doctrine\Omnibase\Database\Walker\TranslatableWalker`'s constants to
  build a schema can read `Base\Database\Schema\TranslationRule`'s (the same values) and do without omnibridge/doctrine.
- An entity of an application that names a column type by a type class's constant
  (`UtcDateTimeImmutableType::NAME`) loads that class when its attributes are read: name it by its name
  (`'utc_datetime_immutable'`) for a description built with no persistence installed, as the core's entities now do.
- doctrine/common is not required by the core: an application that uses its `ClassUtils` itself requires it.

## The behaviours on any persistence (step F7c)

- Nothing to change for an application on Doctrine: the behaviours write the same rows.
- `AbstractAttribute`'s value and change helpers take an optional last argument, the unit of work of the hook's event
  (`getFieldValue($entity, $property, $event->unitOfWork)`...): an application's own attribute passes it to run on any
  persistence; without it Doctrine's metadata answers, as before. `setFieldValue()` and `setPropertyValue()` may
  return a `Base\Database\Entity\EntityValues` then (their result was the manipulator, which nothing read).
- An attribute of an application or a bundle that overrides a Doctrine-typed hook (`onFlush(OnFlushEventArgs ...)`,
  `prePersist(LifecycleEventArgs ...)`) is refused under omnibridge/eloquent: move its logic to the neutral hook
  (`onOnFlush(Lifecycle\OnFlush $event, object $entity, ?string $property)`). omnibase/marketplace's `OrderReference`
  is one (step B).
- An entity dispatcher (`AbstractEventDispatcher`) keeps its Doctrine-typed hooks: under omnibridge/eloquent they get
  doctrine/persistence's `LifecycleEventArgs` over the unit of work. One overriding `postFlush()` is refused there.
- `EntityHydrator::getOriginalEntityData()` reads a PreUpdate's changes from any lifecycle arguments that give them
  (`getEntityChangeSet()`), as from Doctrine's PreUpdateEventArgs.

## The persistence in one click (step F7d)

- Nothing to change with one persistence installed: it is used.
- With omnibridge/doctrine and omnibridge/eloquent both installed, name one, or the container refuses to compile (the
  application refuses to boot under Laravel):

  ```yaml
  # config/packages/base.yaml (config/base.php under Laravel: 'persistence' => 'doctrine')
  base:
      persistence: doctrine     # or eloquent; '%env(OMNIBASE_PERSISTENCE)%' is read when the container is compiled
  ```

  Where the option names none, the environment variable `OMNIBRIDGE_PERSISTENCE` may.
- On `eloquent`, what a site's code takes by the core's contracts (`UnitOfWork`, `Backend`, `Repositories`, the
  repository interfaces, `Users` as the firewall's entity provider) is omnibridge/eloquent's. What it takes by Doctrine's
  service ids (doctrine.orm.entity_manager, a repository by its class, `App\Repository\*` extending omnibridge/doctrine's)
  stays Doctrine's: type on the interfaces to follow the switch.
- An attribute of an application that reads values without the unit of work of its event keeps Doctrine's metadata;
  pass `$event->unitOfWork` to the helpers (step F7c) to follow the switch.

## Stays in the core, deliberately

- The symfony/process patch (`extra.omnibridge.patches`, `process-timeout-from-env`): it applies only
  where symfony/process is installed (skipped otherwise). It moves to omnibridge/symfony when 4.x
  is no longer served: declared by both, the 4.x core's declaration would fail on the sites still on
  4.x (omnibridge/composer applies the bridge's first).
- The former names (`Base\...` aliases): removed at 6.0, after step S of the plan.
