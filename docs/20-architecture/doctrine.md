---
title: The Doctrine mapping, with or without Symfony
order: 28
---

# The Doctrine mapping, with or without Symfony

omnibase assembles part of its entities' mapping at runtime: the attributes
read by `Base\Attributes\AttributeReader` (`#[DiscriminatorEntry]`, `#[Alias]`,
`#[Hierarchify]`, `#[Cache]`, `#[OrderColumn]`, `#[Cascade]`, `#[OrphanRemoval]`,
`#[Vault]`...), the translations' associations and discriminators
(`IntlSubscriber`), the enumerations as column types, the `trash_filter` and
the `vault_filter`, the DQL functions. Since step C6 of the plan "omnibase
agnostique" all of it is wired by `Base\Bridge\Doctrine` - under Symfony through
the bundle, as before, and on an entity manager built by hand without
DoctrineBundle - on doctrine/orm **as published**: nothing needs a member
Doctrine keeps private, and nothing patches doctrine/orm (since 4.0 the core
declares its one patch, on symfony/process, to omnibridge/composer: `extra.omnibridge`).

## Without Symfony

```php
use Doctrine\DBAL\DriverManager;
use Omnibridge\Doctrine\EntityManagers;
use Base\Bridge\Doctrine\Mapping;

$connection = DriverManager::getConnection(['driver' => 'pdo_mysql', 'host' => ..., 'dbname' => ...]);
$entityManager = EntityManagers::create($connection, [
    $vendor.'/omnisense/omnibase/src/Entity',
    $projectDir.'/src/Entity',
    // a bundle's src/Entity...
], configure: Mapping::configure(...));   // the repository factory, before the entity manager exists
$mapping = Mapping::register($entityManager, [
    'project_dir' => $projectDir,          // its src/Enum, its src/Attribute
    'environment' => 'prod',               // the vault filter's
    'types' => [/* a bundle's enumerations, by name */],
    'resolve_target_entities' => [/* interface => class */],
]);

$articles = $entityManager->getRepository(App\Entity\Blog\Article::class)->findBy([], ['id' => 'DESC'], 10);
$article = $entityManager->createQuery('SELECT a FROM App\Entity\Blog\Article a WHERE a.slug = :slug')->setParameter('slug', 'le-fuji')->getOneOrNullResult();
```

`Mapping::register()` is called before the first metadata is read. Doctrine's
own `ClassMetadataFactory` is used; the class names are the core's (D6).

| What | Symfony (the bundle) | By hand (`Mapping::register()`) |
|---|---|---|
| Column types: `datetime`/`datetimetz` in UTC, `array`, `utc_datetime(_immutable)`, the enumerations | `BaseExtension::prependDoctrineTypes()`, `BaseBundle::boot()` → `Base\Bridge\Doctrine\Types::register()` | `Types::register()` |
| Filters (`trash_filter`, `vault_filter`), DQL functions (RAND, FIELD, JSON_*), the default hint | `DoctrineConfigurationPass` → `Base\Bridge\Doctrine\Configuration`'s lists; filters enabled at boot (`Configuration::enableFilters()`) | `Configuration::apply()`, `enableFilters()` |
| The driver: discriminator maps generated, the `resolveDiscriminator` event, the table names checked | `DoctrineConfigurationPass` wraps every entity manager's driver in `Base\Bridge\Doctrine\MappingDriver` | the same wrapper |
| Naming | `naming_strategy: Base\Database\Mapping\NamingStrategy` | set on the configuration |
| The factory | `class_metadata_factory_name: Base\Database\Mapping\ClassMetadataFactory` (kept: the naming strategy of a cached ClassMetadata, `isEntity()`, `getAllClassNames()`) | Doctrine's own |
| Listeners: `AttributeSubscriber`, `IntlSubscriber`, the targets resolved, the entity listeners attached, `TrackingPolicySubscriber`, `ExtensionSubscriber`; on flush `TrasheableSubscriber`, `VersionableSubscriber`, `InverseCollectionCacheSubscriber` | tags `doctrine.event_listener` (config/services/media.php), in their priorities' order | added in that order |
| Repositories | DoctrineBundle's `ContainerRepositoryFactory` (the services) | `Base\Bridge\Doctrine\RepositoryFactory`, set by `Mapping::configure()` as omnibridge/doctrine's `configure:` (from 4d2436d), before the entity manager exists: its own `getRepository()` builds a repository taking a `ManagerRegistry` from `Base\Bridge\Doctrine\Registry`. `$mapping->getRepository()` (deprecated) hands the same one, and is the way for an entity manager built without `configure:` (Doctrine's default factory) |
| `App\Entity\X` = `Base\Entity\X` | `BaseBundle::warmUp()`'s aliases, `AbstractBaseBundle`'s fallback autoloader | `Base\Service\Model\ClassAliases::register()` (the same convention) |

What the hand-built side does not have: the framework's services. The
`AttributeReader` is given no router, request stack, token storage, filesystem
nor event dispatcher (an asset's base path, the user signed in, the routes'
attributes answer without them), `IntlSubscriber` no localizer, and the
parameters are those given (`Base\Service\ArrayParameterBag`). Symfony's
doctrine-bridge types (`uuid`, `ulid`, `date_point`...) are Symfony's alone.

## The enumerations

An enumeration of omnibase (`Base\Database\Type\EnumType`, `SetType`: `ThreadState`, `UserRole`,
an application's or a bundle's) is a plain PHP class since step F2a of the plan "omnibase agnostic":
its constants are its values, `toDatabaseValue()` and `toPHPValue()` its conversions (a SetType's
values joined by commas). On Doctrine each is a `Base\Bridge\Doctrine\EnumTypeAdapter` registered
under its `getStaticName()` (`thread_state`, `user_role`): an `ENUM(...)` column, `SET(...)` for a
SetType, a CLOB on SQLite. `Base\Database\Type\Enumerations::classOf('user_role')` gives the
enumeration of a type's name, whatever persistence registered it.

- Under Symfony an enumeration is still listed in DoctrineBundle's `doctrine.dbal.types` (the core's
  and the application's by `BaseExtension`, a bundle's by its own prepend: omnibase/marketplace);
  `EnumerationTypesPass` takes them out of it (DoctrineBundle instantiates each type's class) and
  gives them to `Base\Bridge\Doctrine\EnumerationsMiddleware`, which registers the adapters when
  the connection is made, before any metadata is read - the moment DoctrineBundle registered them.
  The parameter `base.database.enumerations` lists them by name.
- By hand, `Types::register()` registers them (the folders' enumerations, and those given by name).
- `ClassMetadataManipulator::getDoctrineType()` answers an enumeration's column with an instance of
  the enumeration, as the type was before, so `instanceof EnumType` and its static methods answer as
  they did; `MetadataSnapshot` writes an enumeration's type by the enumeration's class, so the
  recorded metadata is unchanged.

## The behaviours' hooks

omnibase's behaviours (`#[Timestamp]`, `#[Slugify]`, `#[Uploader]`...: `Base\Attributes\AbstractAttribute`)
answer the moments of an entity's life. Since step F2b of the plan "omnibase agnostic" they have
neutral hooks (`onPrePersist(Lifecycle\PrePersist $event, ?string $property)`, ...,
`onOnFlush(Lifecycle\OnFlush $event, object $entity, ?string $property)`) taking omnisense/omnibridge's
events, which carry the entity and the unit of work (`Base\Bridge\Doctrine\DoctrineUnitOfWork` on
Doctrine). `AttributeSubscriber` relays Doctrine's events: the neutral hook first, then the
Doctrine-typed hook of before (`prePersist(LifecycleEventArgs, ClassMetadata, $entity, ?string)`),
kept for the applications' and bundles' attributes that override it. Another persistence dispatches
the neutral events alone. Only the fields a neutral `PreUpdate` hook gave a new value
(`setNewValue()`) are passed on to Doctrine's update; a hook that changes the entity and calls
`recompute()` keeps what Doctrine's change set says.

### The persistence's part of a behaviour

What only a given persistence can do is asked of a small interface of
`Base\Database\Attribute\Backend`, one per behaviour, implemented once per persistence and
registered for its unit of work class with `Backends::register()`; a behaviour asks
`$this->backend($event, VaultBackend::class)` and gets the implementation for the unit of work its
event carries, or an `UnsupportedBehaviourException` when the persistence has none.

| Interface | What it does | Doctrine (`Base\Bridge\Doctrine\Behaviour`) |
|---|---|---|
| `VaultBackend` | an entity written again though its change set is empty (a secured field's new plain value) | `scheduleForUpdate()` |
| `SlugifyBackend` | the flush's pending writes in order; a unique index on the slug's column alone | scheduled insertions then updates; the schema manager |
| `CacheBackend` | what the cache between requests keeps of an entity or an association dropped | the second-level cache's evictions |
| `OrderColumnBackend` | a lazy to-many association keeps its stored positions until read (step F2c) | the PersistentCollection's inner collection swapped |

`AttributeSubscriber` registers Doctrine's (`DoctrineBehaviours::register()`) when it is made, under
Symfony and in `Base\Bridge\Doctrine\Mapping` alike. `#[Uploader]` needs none: the unit of work's
contract covers it (`contains()`, `isNew()`, `recompute()`). The mapping hooks
(`loadClassMetadata()`: `#[Cache]`'s regions, `#[Vault]`'s unique constraints) stay Doctrine's. At step
F3 the Doctrine implementations move to omnibridge/doctrine as they are; omnibridge/eloquent brings its
own at F7 (a persistence without a second-level cache implements `CacheBackend` as nothing to do).

### The entity's shape

A behaviour reads the entity's mapping through `$this->shape($event, $entity)`, a
`Base\Database\Mapping\EntityShape` (one `Omnibridge\Schema\Entity` of the Description: fields and
types, associations and their kind and side, identifier, root, discriminator), never Doctrine's
`ClassMetadata`. The shapes come from the persistence (`EntityShapes`, registered in `Backends` like the
behaviours' parts: `DoctrineShapes` on Doctrine, each class's metadata written by
`MetadataDescription`) or from a Description (`DescriptionShapes`). `#[Alias]`, `#[Associate]` and
`#[OrderColumn]` read it; `ClassMetadataManipulator` answers the association kinds and sides from it
for an entity or a class (a `ClassMetadata` being built is read as it stands), and `EntityHydrator` its
defaults and identifiers. `OrderColumnBackend` keeps a lazy collection's positions until it is read
(Doctrine's PersistentCollection); without it the property is sorted when loaded.

### The core's services on the unit of work

Plan "omnibase agnostic", step F2d. The core's services, listeners, stores, controllers and commands
take omnisense/omnibridge's `UnitOfWork` where they took Doctrine's entity manager, and ask it nothing
of the ORM:

- writing: `persist()`, `remove()`, `flush()`, `refresh()`, `contains()`; an entity's identifier and
  class: `identifier()`, `className()`;
- reading: their repository by its interface (`Base\Repository\*`, a new `User\Identities`, the
  `Passkeys` finder `findOneByCredential()`), or `UnitOfWorks::repositories($unitOfWork)->of($class)`
  for a class named at run time (`Base\Database\Repository\Repositories`);
- an entity's fields: `UnitOfWorks::shapes($unitOfWork)` (the entity's shape, above);
- what only a persistence does, a small interface of `Base\Database\Backend` registered in `Backends`
  like the behaviours' parts:

| Interface | What it does | Who asks | Doctrine (`Base\Bridge\Doctrine\Behaviour`) |
|---|---|---|---|
| `RowLocks` | a row locked for writing while some work runs, in a transaction | the like (`Api\ThreadController`), `thread:publishable` | `DoctrineRowLocks`: a pessimistic write lock |
| `ConditionalUpdates` | one field written only if it still holds a value, in one statement | the sign-in link claimed once (`Auth\UserLinkTokens`) | `DoctrineConditionalUpdates`: a DQL UPDATE |
| `EntityCache` | the cache of entities between requests evicted | `SettingBag` | `DoctrineEntityCache`: the second-level cache |

`Base\Database\UnitOfWorks::of()` gives the unit of work for what a service was handed: the unit of work
itself, or a persistence's manager adapted once (`UnitOfWorks::ADAPTERS`, Doctrine's entity manager to
`DoctrineUnitOfWork`; another persistence registers its own). The services' constructors still accept
the entity manager, as the deprecated other half of a union (`UnitOfWork|EntityManagerInterface`, until
6.0): omnibridge/laravel, the bundles' wiring and the tests hand it to them. Under Symfony the
container's `Omnibridge\Persistence\UnitOfWork` is `DoctrineUnitOfWork` over the entity manager, and
the union is aliased to it, so that an autowired constructor gets the same instance. A
`DoctrineUnitOfWork` registers Doctrine's backends when it is made (`DoctrineBehaviours::ensureRegistered()`),
before any Doctrine event.

What keeps the entity manager, on purpose: the mapping (`ClassMetadataManipulator`,
`ClassMetadataFactory`, `EntityHydrator`), `ServiceEntityRepository`, the Doctrine listeners, the
Doctrine commands (`doctrine:database:import`, `doctrine:array:upgrade`, the schema's charset), the
validators and `CropperType` (the form layer, F5b), `AbstractEventDispatcher` and `BaseNotifier` (F5a),
`DemoAccountFactory` (Doctrine's fixtures), `Base\Console\Command` (its deprecated `$entityManager`, read
by the applications' commands, beside its `$unitOfWork`), `Base\Bridge\Symfony\FrameworkLocator` (since F2f: the entity
manager and the registry BaseTrait handed out, now deprecated there). `tests/Database/UnitOfWorkPathTest`
keeps the converted files off the entity manager.

### The scopes and the pages

Step F2e. The trash and the vault are scopes (omnisense/omnibridge's `Scope`):
`Base\Database\Scope\TrashScope` keeps a `#[Trasheable]` entity while its deletion field is null,
`VaultScope` a `#[Vault]` entity whose vault field is null or names the environment it was enabled with.
Doctrine compiles them as its SQL filters `trash_filter` and `vault_filter` (`TrashFilter`, `VaultFilter`,
same SQL as before); `Base\Bridge\Doctrine\DoctrineBackend` (omnibridge's `Backend`, the service
`Omnibridge\Persistence\Backend` and in `Backends`) enables and disables them by the scope's name.
`TrashManager::withTrash()` asks the Backend; the extension rows of a destroyed entity go through
`Base\Database\Backend\BulkDeletes`.

`Pagination` pages an array, or `Base\Service\Model\Pages` (a count and a slice). A Doctrine `Query` is
adapted to `Base\Bridge\Doctrine\QueryPages`, which keeps what Pagination did with it: the rows grouped by
id through Doctrine's Paginator, the translations joined, the total counted on a clone by CountWalker.
Both move to omnibridge/doctrine at F3; an Eloquent backend brings its own Backend and Pages at F7.

### The lifecycle listeners: logic and Doctrine wiring

The listeners that record something keep their logic in `Base\Database\Lifecycle`, on no
persistence, and their Doctrine part in their former class, which keeps its name and its wiring:

| Logic (core) | Doctrine wiring | What the wiring keeps |
|---|---|---|
| `SettingsInvalidation` (neutral `PostPersist`/`PostUpdate`/`PostRemove`) | `EntitySubscriber\SettingSubscriber` | the relay |
| `TrashRecorder` (purge or trashing, the trash entry) | `EntitySubscriber\TrasheableSubscriber` | the deletion cancelled, the stamp's extra update, the cascade rescued, the second-level cache evicted |
| `RevisionRecorder` (the diff, the values' form, the revision, no repeat) | `EntitySubscriber\VersionableSubscriber` | the updated entities and dirty collections, the revision in the running flush, the trim |

The others are Doctrine's by nature and move as they are at F3: `IntlSubscriber` (the translations'
mapping and discriminators, orphan removal), `ExtensionSubscriber` (extension rows written inside the
flush), `InverseCollectionCacheSubscriber` (the second-level cache), `TrackingPolicySubscriber` (the
mapping), `AttributeSubscriber` itself (the relay, the mapping and query hooks). `DemoAccountLockSubscriber`
reads the request and the session (step F5a); `ConnectionSubscriber`, `UserSubscriber` and
`ThreadSubscriber` listen to Symfony's events (F5c).

## The discriminator maps

A root entity that declares an inheritance and no `#[DiscriminatorMap]` gets
one generated by `MappingDriver`, after the attributes are read: the class and
every subclass the driver knows, each under its class name, which
`#[DiscriminatorEntry]` (`AttributeSubscriber`, `loadClassMetadata`) then
renames (`common`, `marketplace_product`, `forum_topic`...). Doctrine generates
such a map itself, under the classes' lower-cased short names - which collide
here (`App\Entity\Thread\Tag` and `Base\Entity\Thread\Tag` are both `tag`) - and
its `getShortName()` is private: until C6 glitchr/base-plugin made it protected
so that omnibase's factory could answer the class name. The map is now there
before Doctrine looks, with the keys the factory gave.

The `resolveDiscriminator` event (`Base\Database\Events`) is dispatched by the
driver before a class is read, as the factory dispatched it: `IntlSubscriber`
gives a translation class (`ThreadIntl`...) the inheritance and the map of its
translatable.

## The proof

`tests/Bridge/Doctrine/HandBuiltEntityManagerTest` (the harness, or an
application's vendor suite):

- an entity manager built by hand in a PHP process of its own
  (`tests/Bridge/Doctrine/hand-built.php`: Composer's autoloader, a DBAL
  connection, `EntityManagers::create()`, `Mapping::register()`) gives the
  metadata Symfony's gives, class by class - `Base\Bridge\Doctrine\MetadataSnapshot`:
  every public property of each ClassMetadata (table, inheritance,
  discriminator map and value, fields and their types, associations, cache,
  callbacks, entity listeners, repository), what each filter adds to a query of
  it, the completor the attributes filled (aliases, hierarchy), the types
  registered - on the same database, and reads the thread Symfony wrote; the
  process loads neither the kernel nor a bundle;
- Symfony's entity manager gives the metadata it gave before C6
  (`fixtures/doctrine-metadata.json`, recorded on the code of before with
  `DOCTRINE_SNAPSHOT=1`; the discriminator maps and the subclasses compared
  sorted: generated, they come in the order PHP declared the classes).

Since step R14 the mapping driver also reads the classes' declarations rather
than the attributes (omnibridge/doctrine's `DeclarationDriver`): the structure is
described once, outside Doctrine - see [schema.md](schema.md).

The harness runs it on doctrine/orm as released:
`docker compose -f compose.yml -f compose.checkouts.yml run --rm -v <doctrine/orm's release>/src:/srv/demo/vendor/doctrine/orm/src:ro omnibase test`.
