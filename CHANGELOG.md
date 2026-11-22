# Changelog

## 7.2.0

Doctrine dispensable for a package's entities (additive; Phase A of the plan "Doctrine optional in every omnipack"):

- **Entities discovered.** A new `Base\Discovery` kind, `entities` (`'directories' => ['%package%/src/Entity',
  '%kernel.project_dir%/src/Entity']`, `'requires' => 'omnisense/omnibase'`): the mapped classes of the core, of every
  package and of the application, whichever persistence and host. `Discovery::packages()` reads Composer's
  `vendor/composer/installed.json` for the packages that require the core (folder => the PSR-4 namespace of its
  `src/`), what a host without bundles gives; omnibridge/symfony gives its registered omnibase bundles.
  `Discovery::entities()` gives the folders with their namespaces (Doctrine's prefixes), `Discovery::targets()` the
  packages' interfaces carrying `#[ResolveTargetEntity]`, and `Discovery::enumerations()` takes the packages too: a
  package's `src/Enum` is a column type wherever it is installed. omnibridge/symfony 3.2 feeds the same discovery to
  DoctrineBundle, so a package needs no `prependExtensionConfig('doctrine', ...)` for its `src/Entity` or its
  enumerations.
- **`#[Base\Database\Attribute\ResolveTargetEntity(class)]`** on an interface an association targets: the package's
  default target, read by `TargetRule` (every description) and given to Doctrine's `resolve_target_entities` by
  omnibridge/symfony; the application's `resolve_target_entities` prevails.
- **`base.entity_folders`** (`include`: folder => namespace, `exclude`: files and folders), the folders a package adds
  or leaves out by configuration (a profession's entities, a feature that is off) or an application names - *a name
  proposed, to be approved before release*. `EntitySchema`'s options `packages` and `exclude` read them: an excluded
  class is never loaded.
- **`Base\Testing\WithoutPackages`** (omnipack/forge's helper, promoted): namespaces made absent from a test's process
  (`DOCTRINE` by default: doctrine/orm, dbal, persistence, the bundles, symfony/doctrine-bridge, omnibridge/doctrine;
  not doctrine/collections). **`Base\Testing\PackageKernel`**: a package's bundles on the core alone - framework,
  security, Twig, `BaseBundle`, omnibridge/eloquent on SQLite, `createSchema()` through `omnibridge:schema:create`
  (tests/Testing/PackageKernelTest: a fixture package without Doctrine, discovered, a feature left out unloaded).
- `src/Resources/schema.php` (the plain-PHP `omnibridge-schema-create`) describes the Composer packages' entities too.
- `Base\Service\TimeMachine` is declared where DoctrineBundle's registry is installed as well as backup-manager: its
  dumps go through it, and a container without Doctrine did not compile with backup-manager installed.

## 7.1.2

- `base-async` built on @glitchr/flowjs 1.6.4 (it was 1.3.1, no longer on npm, so the bundle could not be built again):
  the scroll position is read as the scroll event comes, not in the frame, where it made the browser lay the page out
  again after the page's own frame work - a scroll that lagged on a busy machine. The package's build dependency is
  the tagged flowjs on GitHub until 1.6.4 is on npm.
- The demonstration's sign-in panel (`@Base/demo/_accounts.html.twig`) prints, under each account's button, the
  identifier and the password it types in the ordinary form (`data-demo-credentials`; `@demo.accounts.identifier`
  and `@demo.accounts.password` in English and French). A visitor shown a site - in a frame, in a presentation - can
  type them as well as click.
- `Finders::sql()` is no longer `@internal`: `DoctrineFinders` implements it (a DebugClassLoader deprecation).
- `DemoAccountRegistry::missingRoles()` takes an account the site left out (`base.demo.exclude`) as a choice, not a gap:
  its roles are no longer asked for, and `demo:reset` no longer warns about them. A real business whose every
  account is approved by hand can offer none.
- `docs/20-architecture/demo.md` and `base.demo.admin_roles`' description: a real business keeps its own management
  roles (a shop's `ROLE_STAFF`) out of its demonstration by listing them there.

## 7.1.1

- `Icons::iconify()` with no adapter knowing a name, not even its question mark (no adapter, or its metadata not
  installed): nothing to draw in the shape the caller asked for, an empty list when wrapped and an empty string
  inside an associative map. It returned an empty list in both cases, so a template reading one icon of a map of
  states (`|first`, `|second`) got an array and failed with "Array to string conversion".

## 7.1.0

omnibridge/doctrine 4.0 (and omnibridge/eloquent 2.1) read the `datetime`, `datetimetz` and `utc_datetime` columns as
`\DateTimeImmutable`, in UTC; the core takes such a value wherever it took a `\DateTime`. require-dev:
omnibridge/doctrine ^4.0, omnibridge/symfony ^3.1, doctrine/dbal ^4.6, doctrine/orm ^3.8 (the CI's harness on
omnibridge/doctrine 4.x).
- `Base\Entity\User\Complaint`: `createdAt` and `handledAt` are `\DateTimeInterface` (property, `getCreatedAt()`,
  `setCreatedAt()`, `getHandledAt()`; they were `\DateTime`), made as `\DateTimeImmutable`.
- Widened to `\DateTimeInterface` (a parameter, no BC break): `FunctionTwigExtension::datetime()`, `countdown()` and
  `progress()` (`|datetime` takes an immutable moment), `Thread::isPublished()`, `TrashRecorder::entry()`,
  `DateTimePickerField::setDefault()`, `TimeMachine::setTimeLimit()`, `Launcher::yearsSince()`,
  `IntlDateTime::__construct()` and `createFromDateTime()`, and the helpers `cast_datetime()` (a mutable copy of
  any moment, as the clone it made), `daydiff()`, `ceil_datetime()`, `floor_datetime()`, `round_datetime()`,
  `datetime_is_between()`, `date_is_between()`, `time_is_between()`.
- `CacheableTrait::__toKey()` keys an immutable moment by its timestamp too (it was left out of the key);
  `Launcher::date()` reads a stored immutable launch date; `#[Timestamp(immutable: true)]`'s value no longer breaks
  `getValue()` (typed `\DateTime`, it returned a `\DateTimeImmutable`).
- Tests: the types are read from the connection's type provider (`Omnibridge\Doctrine\Omnibase\Types::provider()`,
  `registerEnumerations($registry, ...)`), no longer from DBAL's static registry or `Types::register()`
  (SchemaManagerTest, PasskeyTypesTest, passkey-without-webauthn.php, AppEnumerationsTest, CompiledEnumerationsTest,
  UtcDateTimeTypeRegistrationTest, SchemaDescriptionTest); SchemaManagerTest builds its columns by type name
  (DBAL 4.6); UtcDateTimeTypeTest reads `utc_datetime` as a `\DateTimeImmutable`.

## 7.0.4

- `cache:clear`'s automatic second clear ran while the first still held the cache folder's lock: the second process
  skipped ("Another cache:clear is already running"), so on a fresh tree one `cache:clear` left the clear counter at
  one and made none of the public folder's links (`public/uploads`, `images`, `wysiwyg`); a local upload then had no
  public address until a second manual `cache:clear`. The lock is released before the second clear starts.

## 7.0.3

- No `extra.branch-alias` any more (the ensemble has none, foundation Q4): the release workflow no longer checks the
  tag against one (`branch-alias: false`); the branch 7.x is Composer's 7.x-dev as it is.
- The harness image installs omnibridge/eloquent beside omnibridge/doctrine (`Base\Testing\Engines`, the parity tests
  of the core and the plugins) and its application names its persistence (`base.persistence: doctrine`).
- Tests: BareTest accepts the src/Base a persistence bridge declares under `Base\` (omnibridge/doctrine 3.0); the
  recorded Doctrine metadata and finder parse follow the 7.0 ensemble (forge's seat listener is a neutral lifecycle
  listener, TimeEntry and Wishlist\Reservation name their repositories, the filters are omnibridge/doctrine's,
  a finder asked of `Base\Entity\User` queries that class, `ServiceEntityParser` is `DqlCompiler`).

## 7.0.2

- `Base\Database\Repository\EntityRepositories`: its PHPDoc takes any repository, `ServiceEntityRepository<covariant
  object>` (`register()`, `repositoryClass()`, the registry): 7.0.1's `ServiceEntityRepository<object>` refused a
  repository of a given entity (`Base\Repository\ThreadRepository`, `ServiceEntityRepository<Thread>`), as
  omnibridge/eloquent's PHPStan reported.
- The harness image (docker/Dockerfile) no longer requires `omnibridge/doctrine:1.x-dev` among its bundles, which
  conflicted with the 3.x it requires and with the 2.0 bundles: the release's harness job built no image.

## 7.0.1

`Base\Mapping\OrderBy`'s value is documented as `array<string, string|\UnitEnum>`: a direction may be an enumeration
(`SortDirection::Ascending`), as omnibase/mailbox writes it; 7.0.0's PHPDoc took strings only, and an application's
PHPStan reported the enumeration.

## 7.0.0

omnisense/omnibase: the core of omnibase sites under the agnostic `Base\` namespace (an application overrides
`Base\X` by `App\X`, the same path), on any framework and persistence: the entities (written in `Base\Mapping`), the
agnostic repositories (`Base\Repository\*` over `Base\Database\Repository\ServiceEntityRepository`: the finder
language, `findBy()`'s criteria array, `count()`, `findValuesBy()`, `updateBy()`, `deleteBy()`, `lockOneBy()`, `sql()`: every
query written once; the same classes on
Doctrine and on Eloquent, the engine given by `Finders`), one `Base\Service\Paginator` (a repository with criteria and
an order, a finder's result, or rows), the behaviour attributes (`Base\Database\Attribute\*`) on the neutral
lifecycle, the generic contracts of omnisense/omnibridge under `Base\Contract\*`, the options (`base.*`), the
services, templates, translations and assets. The framework and ORM glue lives in the omnibridge/* bridges, which
add their classes under `Base\` too; a site or a bundle names only `Base\` and `App\`.
