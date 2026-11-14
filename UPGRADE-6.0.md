# Upgrading from omnisense/omnibase 5.x to 6.0

6.0 removes what 5.x kept for the sites and bundles written before the plan "omnibase agnostic": the **former names**
of the classes the core moved to omnibridge/doctrine and omnibridge/symfony (377 class aliases), and the **deprecated
Doctrine-, Twig- and Symfony-typed entry points** (the constructors' union types, the entity dispatchers' former hooks,
BaseTrait's framework accessors). Nothing else changes: the entities, the schema, the routes, the templates and the
configuration tree (`base:`) are those of 5.1. No migration.

A site or a bundle that already passes on 5.1 with no former name and no deprecation (every omnibase/* bundle's
current tag does) has only its Composer constraints to change.

## 1. Composer

| Package | 5.x | 6.0 |
|---|---|---|
| `omnisense/omnibase` | `^5.1` | `^6.0` (a bundle: `^5.1 \|\| ^6.0` when its code is on the new names) |
| `omnibridge/symfony` | `^1.1` | `^2.0` (2.x conflicts with omnibase < 6.0; 1.x with omnibase 6) |
| `omnibridge/doctrine` | `^1.0` | `^2.0` (2.x also runs omnibase 5.1) |
| `omnibridge/laravel` | `^1.0` | `^1.2` |
| `omnibridge/eloquent` | `^1.0` | `^1.1` |

omnisense/omnibase 6.0 declares `conflict` with omnibridge/symfony < 2.0, omnibridge/doctrine < 2.0, omnibridge/laravel
< 1.2 and omnibridge/eloquent < 1.1: Composer refuses an install that would mix them. omnibridge/twig is unchanged (^1.0).

## 2. Find what still names a former name

Every former name is listed in section 4. A scan of a site or a bundle (its src/, config/, templates/, tests/):

    python3 -I l6a-c-scan.py <dir>        # the omnibase agnostic plan's scanner: former names and deprecated hooks

or, by hand: `grep -rnE 'Base\\\\(Bridge|Repository\\\\.*Repository|Field\\\\Type|Form\\\\(Type|Extension|Transformer|Traits)|Security|Routing|Subscriber|Controller|Console\\\\Command|Database\\\\(Mapping|Filter|Type|Repository)|DatabaseSubscriber|EntitySubscriber|Bundle|BaseBundle)'`.
Then rename each `use`, `extends`, `::class`, YAML service id and string to the new name (section 4). A former name that
is still written somewhere fails on 6.0 with `Class "Base\..." not found` (PHP) or `You have requested a non-existent
service "Base\..."` (Symfony).

The places a site usually names them:

| Where | 5.x | 6.0 |
|---|---|---|
| config/bundles.php | `Base\BaseBundle::class` | `Omnibridge\Symfony\Omnibase\BaseBundle::class` |
| a bundle's class | `extends Base\Bundle\AbstractBaseBundle` (and `AbstractBaseExtension`, `AbstractBaseConfiguration`) | `Omnibridge\Symfony\Omnibase\Bundle\AbstractBase*` |
| config/packages/doctrine.yaml | `naming_strategy: Base\Database\Mapping\NamingStrategy`, `class_metadata_factory_name: Base\Database\Mapping\ClassMetadataFactory` | `Omnibridge\Doctrine\Omnibase\Database\Mapping\NamingStrategy`, `...\ClassMetadataFactory` |
| config/packages/security.yaml | `Base\Security\LoginFormAuthenticator`, `RescueFormAuthenticator`, `UserChecker`, `AccessTokenHandler`, `Base\Repository\User\PasskeyRepository` | `Omnibridge\Symfony\Omnibase\Security\*`, `Omnibridge\Doctrine\Omnibase\Repository\User\PasskeyRepository` |
| config/packages/rate_limiter.yaml | `Base\Security\SignInGuard` | `Omnibridge\Symfony\Omnibase\Security\SignInGuard` |
| config/packages/framework.yaml | `error_controller: Base\Controller\ErrorController::show` | `Omnibridge\Symfony\Omnibase\Controller\ErrorController::show` |
| src/Repository/*Repository.php | `extends Base\Repository\UserRepository` (any core repository) | `extends Omnibridge\Doctrine\Omnibase\Repository\UserRepository` |
| a repository of the application's own entity | `extends Base\Database\Repository\ServiceEntityRepository` | `Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository` |
| forms, CRUD fields, settings | `Base\Field\Type\*`, `Base\Form\Type\*`, `Base\Form\FormProxyInterface`, `Base\Form\FormProcessorInterface` | `Omnibridge\Symfony\Omnibase\Field\Type\*`, `...\Form\*` |
| autowired types | `Base\Routing\AdvancedRouterInterface`, `Base\Bridge\Symfony\ScreenResponder`, `Base\Service\ParameterBag` | `Omnibridge\Symfony\Omnibase\Routing\AdvancedRouterInterface`, `...\ScreenResponder`, `...\Service\ParameterBag` |
| translations keyed by a class | `Base\Controller\Api\ThreadController` (controllers+intl-icu.*.yaml) | the controller's new name |

Two behaviours that follow the names:

- **A kind's form type is its new name.** `Base\Field\Kinds` / omnibridge/symfony's `SymfonyKinds` give
  `Omnibridge\Symfony\Omnibase\Field\Type\SelectType` where 5.x gave `'Base\Field\Type\SelectType'`, and so does
  omnibridge/doctrine's `ClassMetadataManipulator` (`form_type`). Code comparing `getFormType()` with `===` against a
  former name's string compares with the new `::class` (an `is_a(..., true)` keeps working on both).
- **The App\ classes stand for the bridges' classes.** `App\Repository\X` is `Omnibridge\Doctrine\Omnibase\Repository\X`
  and `App\Form\X` is `Omnibridge\Symfony\Omnibase\Form\X` where the application declares none (5.x: their former
  names). The stubs omnibridge/composer writes extend the new names: `composer dump-autoload` (or the next install)
  rewrites them.

## 3. The removed entry points, and what replaces them

### Constructors: the contract only

Each of these accepted, until 5.x, a framework or Doctrine object beside omnisense/omnibridge's contract (a union
type) and adapted it. 6.0 takes the contract only. Under Symfony and Laravel the containers already give the contract:
only a service built by hand changes.

| Parameter | 5.x accepted too | 6.0: give |
|---|---|---|
| `UnitOfWork $unitOfWork` - `TrashManager`, `VersionManager`, `GroupManager`, `Invitations`, `Signatures`, `SettingBag`, `WebPushService`, `CollabRoomResolver`, `HeadingEnhancer`, `UserTracker`, `ConnectionTracker`, `ConnectionSubscriber`, `UserStore`, `Auth\Listener\{TrackConnections, UpdateAccount, WelcomeBack}`, `InAppChannel`, `BaseNotifier`, `AbstractEventDispatcher`, `EntityHydrator` | `Doctrine\ORM\EntityManagerInterface` | the unit of work (`Omnibridge\Persistence\UnitOfWork`, autowired), or `Base\Database\UnitOfWorks::of($entityManager)` |
| `EntityHydrator::__construct(..., ?EntityShapes $shapes)` | omnibridge/doctrine's `ClassMetadataManipulator` | `UnitOfWorks::shapes($unitOfWork)`, or nothing (the default) |
| `Views $views` - `BaseNotifier`, `EmailComposer` (constructor and `render()`), `InlineImages`, `Sitemapper`, `FileService`, `MediaService`, `WysiwygEnhancer`, `QrSheet`, `AbstractSharingAdapter`, `NotificationMessages` | `Twig\Environment` | `Omnibridge\Contract\Views` (autowired), or `new Omnibridge\Twig\TwigViews($twig)` |
| `?Requests $requests` - `AbstractEventDispatcher`, `Referrer`, `SpamChecker`, `BrowserPlusChannel`; omnibridge/doctrine 2.0's `DemoAccountLockSubscriber` | `Symfony\Component\HttpFoundation\RequestStack` | `Omnibridge\Contract\Requests`, or `new Omnibridge\Symfony\Contract\StackRequests($requestStack)` |
| `?Roles $roleHierarchy` - `SecurityPolicy` | `Symfony\Component\Security\Core\Role\RoleHierarchyInterface` | `Omnibridge\Contract\Roles`, or `new Omnibridge\Symfony\Contract\HierarchyRoles($roleHierarchy)` |
| `Access $authorizationChecker`, `?Access $tokenStorage` - `Launcher`, `MaintenanceProvider`; `Access $access` - `UserSubscriber` | Symfony's `AuthorizationCheckerInterface`, `TokenStorageInterface` | `Omnibridge\Contract\Access` |
| `UnitOfWork $doctrine` - `DemoAccountFactory` | `Doctrine\Persistence\ManagerRegistry` | the unit of work (a fixture's `ObjectManager` given to a call is still used as it is) |

Removed with them: `Base\View\ViewsAdapter`, `Base\Http\RequestsOf` (their adaptation), `EmailComposer::views()`.
omnibridge/symfony 2.0 no longer registers the union aliases `Doctrine\ORM\EntityManagerInterface|Omnibridge\Persistence\UnitOfWork`
and `Omnibridge\Contract\Views|Twig\Environment`.

### Entity dispatchers (Base\EntityDispatcher)

| 5.x | 6.0 |
|---|---|
| `onPersist(LifecycleEventArgs $event)` | `persisting(Omnibridge\Persistence\Lifecycle\PrePersist $event): void` |
| `onUpdate(LifecycleEventArgs $event)` | `updating(Lifecycle\PreUpdate $event): void` (the changes: `$event->changes()`; the entity before: `EntityHydrator::getOriginalEntity($event)`) |
| `onRemove(LifecycleEventArgs $event)` | `removing(Lifecycle\PreRemove $event): void` |
| `dispatchEvents(LifecycleEventArgs $event)` | nothing: the events noted are dispatched after the write by `lifecycle()` or Doctrine's `postPersist()`/`postUpdate()`/`postRemove()` |
| `AbstractEventDispatcher::FORMER_HOOKS`, `formerHooks()` | gone (every dispatcher is on the neutral hooks) |
| `AbstractEventDispatcher::getEntityManager()`, `$entityManager` | `getUnitOfWork()`, `$unitOfWork` |
| `EventDispatcherInterface::dispatchEvents()`, `onPersist()`, `onUpdate()`, `onRemove()` | gone: the interface declares `supports()` and `addEvent()` |
| `AbstractEvent::getObjectManager()`, `EventInterface::getObjectManager()` | `getUnitOfWork()` (declared by `EventInterface`) |

A dispatcher that still declares `onPersist()` is no longer called through it: it is a method of its own, nothing
calls it. Doctrine's entries (`prePersist()` ... `postRemove()`) stay, and call the neutral hooks.

### Notifier (Base\Notifier\Abstract\BaseNotifier)

| 5.x | 6.0 |
|---|---|
| `getEnvironment()`, `$twig` | `getViews()`, `$views` |
| `$entityManager` | `$unitOfWork` |

### BaseTrait's framework accessors (Base\Traits\FrameworkAccessorsTrait)

The trait is gone, and BaseTrait (and `Base\Service\Locator`, `BaseService`, every entity) no longer answers:

| 5.x | 6.0 |
|---|---|
| `getRouter()` (`->generate(...)`) | `Base\Contract\Link::to()`, `Runtime::urls()` |
| `getTwig()` | `Omnibridge\Contract\Views` (`Runtime::views()`) |
| `getEntityManager()`, `getDoctrine()`, `getObjectManager()` | the unit of work (`Runtime::unitOfWork()`, an injected `Omnibridge\Persistence\UnitOfWork`) |
| `getRepository()` | the repository by its interface (`Base\Repository\*`), or `Base\Database\UnitOfWorks::repositories($unitOfWork)->of($class)` |
| `getClassMetadataManipulator()` | `Base\Database\UnitOfWorks::shapes($unitOfWork)`, `BaseTrait::isEntity()` |
| `getTokenStorage()`, `getRequestStack()`, `getFirewallMap()` | `Runtime::access()`, `Runtime::sessions()`, `Runtime::routes()` |
| `getService()` | the contracts the facade answered (`Runtime::environment()`, `Runtime::access()`...) |
| `getSlugger()` | symfony/string's `AsciiSlugger`, or an injected slugger |

Host code under Symfony asks the same services of `Omnibridge\Symfony\Omnibase\FrameworkLocator`. A template that
called them on an entity (`thread.getRepository()`: the core's thread search page did) asks its controller instead
(the search page reads `sections`, `Base\Thread\ThreadSearch::sections()`).

### Others

| 5.x | 6.0 |
|---|---|
| `Launcher::redirectOnDeny()`, `MaintenanceProvider::redirectOnDeny()` (and on their interfaces), `Base\Request\EventDecisions` | the rules `Base\Request\Rule\LaunchPage` and `MaintenancePage`, which omnibridge/symfony's `SecuritySubscriber` applies |
| `Base\Traits\CacheClearTrait` | `Base\Console\Task\CacheClear` (omnibridge/symfony's `cache:clear` runs it) |
| `Base\Database\Mapping\EntityShapes` without `classes()` | an implementation declares `classes(): array` (both of the core's and omnibridge/doctrine's do) |
| src/Resources/former-names.php (the aliasing autoloader) | gone; `Base\Database\FormerNames` and `former-names.{doctrine,symfony}.php` stay as the record of the renames (data, no alias) |

### omnibridge/symfony 2.0

- Every service is registered under its class's new name only: the former ids (`Base\Security\UserChecker`,
  `Base\Form\FormProxy`, `Base\Repository\...`, `Base\Controller\X`...) are gone, with `FormerServiceIdsPass` and
  `FormerFormTypesPass` (Symfony Form knew each moved type under both names). The controllers are public services under
  their names (what `framework.error_controller` or a `forward()` names).
- `SymfonyKinds::MAP` gives the new names; `Form\FormTypes::same()`/`in()` compare names as written (a leading
  backslash aside), no former name any more.
- `App\Repository\X` and `App\Form\X` are mapped from the bridges' namespaces (section 2).

### omnibridge/doctrine 2.0

- `ClassMetadataManipulator` gives `form_type` by the new names (`Omnibridge\Symfony\Omnibase\Field\Type\*`).
- `DemoAccountLockSubscriber` takes `?Omnibridge\Contract\Requests` only (no `RequestStack`).
- `MetadataSnapshot` records a repository class by its former name, as it did a filter and a type: a snapshot taken on
  5.x compares equal.

### Not in 6.0

- The entities' `#[ORM\...]` attributes (the vocabulary step, E3 of the plan): unchanged, `Doctrine\ORM\Mapping` still.
- AbstractAttribute's helpers without a unit of work, and `EntityValues` without one, still read Doctrine's metadata
  through omnibridge/doctrine's `ClassMetadataManipulator` (until E3).
- omnibridge/symfony keeps its `Legacy\` classes (the parents of its commands, subscribers and voters) and
  `Console\Command`'s protected `$entityManager`; omnibase/admin keeps its own deprecated entry points
  (`Filter::applyWith()`, the `*Entity()` write hooks, the QueryBuilder list hooks) until its 2.0.

## 4. Every former name and its replacement

Generated from src/Resources/former-names.doctrine.php (to omnibridge/doctrine) and former-names.symfony.php (to
omnibridge/symfony). A service id that was a former name is the new name.

377 names.

### `Base\`

| 5.x | 6.0 |
|---|---|
| `Base\BaseBundle` | `Omnibridge\Symfony\Omnibase\BaseBundle` |

### `Base\Bridge\Doctrine\Behaviour\`

| 5.x | 6.0 |
|---|---|
| `Base\Bridge\Doctrine\Behaviour\DoctrineBehaviours` | `Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineBehaviours` |
| `Base\Bridge\Doctrine\Behaviour\DoctrineBulkDeletes` | `Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineBulkDeletes` |
| `Base\Bridge\Doctrine\Behaviour\DoctrineCacheBackend` | `Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineCacheBackend` |
| `Base\Bridge\Doctrine\Behaviour\DoctrineConditionalUpdates` | `Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineConditionalUpdates` |
| `Base\Bridge\Doctrine\Behaviour\DoctrineEntityCache` | `Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineEntityCache` |
| `Base\Bridge\Doctrine\Behaviour\DoctrineOrderColumnBackend` | `Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineOrderColumnBackend` |
| `Base\Bridge\Doctrine\Behaviour\DoctrineRowLocks` | `Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineRowLocks` |
| `Base\Bridge\Doctrine\Behaviour\DoctrineSlugifyBackend` | `Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineSlugifyBackend` |
| `Base\Bridge\Doctrine\Behaviour\DoctrineVaultBackend` | `Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineVaultBackend` |

### `Base\Bridge\Doctrine\`

| 5.x | 6.0 |
|---|---|
| `Base\Bridge\Doctrine\Configuration` | `Omnibridge\Doctrine\Omnibase\Configuration` |
| `Base\Bridge\Doctrine\DoctrineBackend` | `Omnibridge\Doctrine\Omnibase\DoctrineBackend` |
| `Base\Bridge\Doctrine\DoctrineEntities` | `Omnibridge\Doctrine\Omnibase\DoctrineEntities` |
| `Base\Bridge\Doctrine\DoctrineRepositories` | `Omnibridge\Doctrine\Omnibase\DoctrineRepositories` |
| `Base\Bridge\Doctrine\DoctrineShapes` | `Omnibridge\Doctrine\Omnibase\DoctrineShapes` |
| `Base\Bridge\Doctrine\DoctrineUnitOfWork` | `Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork` |
| `Base\Bridge\Doctrine\EnumTypeAdapter` | `Omnibridge\Doctrine\Omnibase\EnumTypeAdapter` |
| `Base\Bridge\Doctrine\EnumerationsMiddleware` | `Omnibridge\Doctrine\Omnibase\EnumerationsMiddleware` |
| `Base\Bridge\Doctrine\Mapping` | `Omnibridge\Doctrine\Omnibase\Mapping` |
| `Base\Bridge\Doctrine\MappingDriver` | `Omnibridge\Doctrine\Omnibase\MappingDriver` |
| `Base\Bridge\Doctrine\MetadataSnapshot` | `Omnibridge\Doctrine\Omnibase\MetadataSnapshot` |
| `Base\Bridge\Doctrine\MySQLSchemaManager` | `Omnibridge\Doctrine\Omnibase\MySQLSchemaManager` |
| `Base\Bridge\Doctrine\QueryPages` | `Omnibridge\Doctrine\Omnibase\QueryPages` |
| `Base\Bridge\Doctrine\Registry` | `Omnibridge\Doctrine\Omnibase\Registry` |
| `Base\Bridge\Doctrine\RepositoryFactory` | `Omnibridge\Doctrine\Omnibase\RepositoryFactory` |
| `Base\Bridge\Doctrine\SchemaManagerFactory` | `Omnibridge\Doctrine\Omnibase\SchemaManagerFactory` |
| `Base\Bridge\Doctrine\Types` | `Omnibridge\Doctrine\Omnibase\Types` |

### `Base\Bridge\Symfony\`

| 5.x | 6.0 |
|---|---|
| `Base\Bridge\Symfony\ActionArguments` | `Omnibridge\Symfony\Omnibase\ActionArguments` |
| `Base\Bridge\Symfony\ActionResults` | `Omnibridge\Symfony\Omnibase\ActionResults` |
| `Base\Bridge\Symfony\AuthListeners` | `Omnibridge\Symfony\Omnibase\AuthListeners` |
| `Base\Bridge\Symfony\FlysystemFiles` | `Omnibridge\Symfony\Omnibase\FlysystemFiles` |
| `Base\Bridge\Symfony\FrameworkLocator` | `Omnibridge\Symfony\Omnibase\FrameworkLocator` |
| `Base\Bridge\Symfony\LocalizerLocales` | `Omnibridge\Symfony\Omnibase\LocalizerLocales` |
| `Base\Bridge\Symfony\MediaPictures` | `Omnibridge\Symfony\Omnibase\MediaPictures` |
| `Base\Bridge\Symfony\RouterRoutes` | `Omnibridge\Symfony\Omnibase\RouterRoutes` |
| `Base\Bridge\Symfony\ScreenResponder` | `Omnibridge\Symfony\Omnibase\ScreenResponder` |
| `Base\Bridge\Symfony\ServiceMedia` | `Omnibridge\Symfony\Omnibase\ServiceMedia` |
| `Base\Bridge\Symfony\SettingBagWarmer` | `Omnibridge\Symfony\Omnibase\SettingBagWarmer` |
| `Base\Bridge\Symfony\SymfonyScreenContext` | `Omnibridge\Symfony\Omnibase\SymfonyScreenContext` |
| `Base\Bridge\Symfony\WysiwygTexts` | `Omnibridge\Symfony\Omnibase\WysiwygTexts` |

### `Base\Bridge\Symfony\Form\`

| 5.x | 6.0 |
|---|---|
| `Base\Bridge\Symfony\Form\SymfonyKinds` | `Omnibridge\Symfony\Omnibase\Form\SymfonyKinds` |

### `Base\Bundle\`

| 5.x | 6.0 |
|---|---|
| `Base\Bundle\AbstractBaseBundle` | `Omnibridge\Symfony\Omnibase\Bundle\AbstractBaseBundle` |
| `Base\Bundle\AbstractBaseConfiguration` | `Omnibridge\Symfony\Omnibase\Bundle\AbstractBaseConfiguration` |
| `Base\Bundle\AbstractBaseExtension` | `Omnibridge\Symfony\Omnibase\Bundle\AbstractBaseExtension` |

### `Base\Cache\Abstract\`

| 5.x | 6.0 |
|---|---|
| `Base\Cache\Abstract\AbstractLocalCacheWarmer` | `Omnibridge\Symfony\Omnibase\Cache\Abstract\AbstractLocalCacheWarmer` |

### `Base\Cache\Clearer\`

| 5.x | 6.0 |
|---|---|
| `Base\Cache\Clearer\CodeCachesClearer` | `Omnibridge\Symfony\Omnibase\Cache\Clearer\CodeCachesClearer` |

### `Base\Cache\Warmer\`

| 5.x | 6.0 |
|---|---|
| `Base\Cache\Warmer\AttributeCacheWarmer` | `Omnibridge\Symfony\Omnibase\Cache\Warmer\AttributeCacheWarmer` |
| `Base\Cache\Warmer\IconCacheWarmer` | `Omnibridge\Symfony\Omnibase\Cache\Warmer\IconCacheWarmer` |
| `Base\Cache\Warmer\LocalizerCacheWarmer` | `Omnibridge\Symfony\Omnibase\Cache\Warmer\LocalizerCacheWarmer` |
| `Base\Cache\Warmer\MetadataCacheWarmer` | `Omnibridge\Symfony\Omnibase\Cache\Warmer\MetadataCacheWarmer` |
| `Base\Cache\Warmer\SpreadsheetCacheWarmer` | `Omnibridge\Symfony\Omnibase\Cache\Warmer\SpreadsheetCacheWarmer` |
| `Base\Cache\Warmer\ThemizerCacheWarmer` | `Omnibridge\Symfony\Omnibase\Cache\Warmer\ThemizerCacheWarmer` |
| `Base\Cache\Warmer\WebpackCacheWarmer` | `Omnibridge\Symfony\Omnibase\Cache\Warmer\WebpackCacheWarmer` |

### `Base\Console\`

| 5.x | 6.0 |
|---|---|
| `Base\Console\Application` | `Omnibridge\Symfony\Omnibase\Console\Application` |
| `Base\Console\Command` | `Omnibridge\Symfony\Omnibase\Console\Command` |
| `Base\Console\Console` | `Omnibridge\Symfony\Omnibase\Console\Console` |

### `Base\Console\Command\`

| 5.x | 6.0 |
|---|---|
| `Base\Console\Command\AutocompleteEntitiesCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\AutocompleteEntitiesCommand` |
| `Base\Console\Command\BaseMappingCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\BaseMappingCommand` |
| `Base\Console\Command\CacheClearCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\CacheClearCommand` |
| `Base\Console\Command\CacheClearSessionsCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\CacheClearSessionsCommand` |
| `Base\Console\Command\DemoResetCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\DemoResetCommand` |
| `Base\Console\Command\DoctrineArrayUpgradeCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\DoctrineArrayUpgradeCommand` |
| `Base\Console\Command\DoctrineDatabaseImportCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\DoctrineDatabaseImportCommand` |
| `Base\Console\Command\DoctrineSchemaCharsetCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\DoctrineSchemaCharsetCommand` |
| `Base\Console\Command\EntityDiscriminatorCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\EntityDiscriminatorCommand` |
| `Base\Console\Command\IconEntitiesCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\IconEntitiesCommand` |
| `Base\Console\Command\IconEnumsCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\IconEnumsCommand` |
| `Base\Console\Command\MailTrapCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\MailTrapCommand` |
| `Base\Console\Command\NotifierCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\NotifierCommand` |
| `Base\Console\Command\NotifierMailTestCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\NotifierMailTestCommand` |
| `Base\Console\Command\ThreadPublishableCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\ThreadPublishableCommand` |
| `Base\Console\Command\TimeMachineSnapshotBackupCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\TimeMachineSnapshotBackupCommand` |
| `Base\Console\Command\TimeMachineSnapshotCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\TimeMachineSnapshotCommand` |
| `Base\Console\Command\TimeMachineSnapshotRestoreCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\TimeMachineSnapshotRestoreCommand` |
| `Base\Console\Command\TimeMachineStorageCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\TimeMachineStorageCommand` |
| `Base\Console\Command\TranslationControllersCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\TranslationControllersCommand` |
| `Base\Console\Command\TranslationCrudCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\TranslationCrudCommand` |
| `Base\Console\Command\TranslationEntitiesCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\TranslationEntitiesCommand` |
| `Base\Console\Command\TranslationEnumsCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\TranslationEnumsCommand` |
| `Base\Console\Command\TranslationSettingsCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\TranslationSettingsCommand` |
| `Base\Console\Command\TrashPurgeCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\TrashPurgeCommand` |
| `Base\Console\Command\UploaderEntitiesCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\UploaderEntitiesCommand` |
| `Base\Console\Command\UploaderImagesCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\UploaderImagesCommand` |
| `Base\Console\Command\UploaderImagesCropCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\UploaderImagesCropCommand` |
| `Base\Console\Command\UserCreateCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\UserCreateCommand` |
| `Base\Console\Command\UserNotificationCommand` | `Omnibridge\Symfony\Omnibase\Console\Command\UserNotificationCommand` |

### `Base\Controller\Api\`

| 5.x | 6.0 |
|---|---|
| `Base\Controller\Api\ThreadController` | `Omnibridge\Symfony\Omnibase\Controller\Api\ThreadController` |

### `Base\Controller\Client\`

| 5.x | 6.0 |
|---|---|
| `Base\Controller\Client\ContactController` | `Omnibridge\Symfony\Omnibase\Controller\Client\ContactController` |
| `Base\Controller\Client\NotificationController` | `Omnibridge\Symfony\Omnibase\Controller\Client\NotificationController` |
| `Base\Controller\Client\ThreadSearchController` | `Omnibridge\Symfony\Omnibase\Controller\Client\ThreadSearchController` |
| `Base\Controller\Client\UserProfileController` | `Omnibridge\Symfony\Omnibase\Controller\Client\UserProfileController` |
| `Base\Controller\Client\UserSearchController` | `Omnibridge\Symfony\Omnibase\Controller\Client\UserSearchController` |
| `Base\Controller\Client\UserSettingsController` | `Omnibridge\Symfony\Omnibase\Controller\Client\UserSettingsController` |

### `Base\Controller\`

| 5.x | 6.0 |
|---|---|
| `Base\Controller\DemoController` | `Omnibridge\Symfony\Omnibase\Controller\DemoController` |
| `Base\Controller\ErrorController` | `Omnibridge\Symfony\Omnibase\Controller\ErrorController` |
| `Base\Controller\LocalizerController` | `Omnibridge\Symfony\Omnibase\Controller\LocalizerController` |
| `Base\Controller\MainController` | `Omnibridge\Symfony\Omnibase\Controller\MainController` |
| `Base\Controller\ProfilerController` | `Omnibridge\Symfony\Omnibase\Controller\ProfilerController` |
| `Base\Controller\RescueController` | `Omnibridge\Symfony\Omnibase\Controller\RescueController` |
| `Base\Controller\SecurityController` | `Omnibridge\Symfony\Omnibase\Controller\SecurityController` |
| `Base\Controller\ShortLinkController` | `Omnibridge\Symfony\Omnibase\Controller\ShortLinkController` |
| `Base\Controller\SitemapController` | `Omnibridge\Symfony\Omnibase\Controller\SitemapController` |
| `Base\Controller\WidgetController` | `Omnibridge\Symfony\Omnibase\Controller\WidgetController` |

### `Base\Controller\UX\`

| 5.x | 6.0 |
|---|---|
| `Base\Controller\UX\AutocompleteController` | `Omnibridge\Symfony\Omnibase\Controller\UX\AutocompleteController` |
| `Base\Controller\UX\AutovalidateController` | `Omnibridge\Symfony\Omnibase\Controller\UX\AutovalidateController` |
| `Base\Controller\UX\DropzoneController` | `Omnibridge\Symfony\Omnibase\Controller\UX\DropzoneController` |
| `Base\Controller\UX\EditorController` | `Omnibridge\Symfony\Omnibase\Controller\UX\EditorController` |
| `Base\Controller\UX\MediaController` | `Omnibridge\Symfony\Omnibase\Controller\UX\MediaController` |

### `Base\DatabaseSubscriber\`

| 5.x | 6.0 |
|---|---|
| `Base\DatabaseSubscriber\AttributeSubscriber` | `Omnibridge\Doctrine\Omnibase\DatabaseSubscriber\AttributeSubscriber` |
| `Base\DatabaseSubscriber\DemoAccountLockSubscriber` | `Omnibridge\Doctrine\Omnibase\DatabaseSubscriber\DemoAccountLockSubscriber` |
| `Base\DatabaseSubscriber\IntlSubscriber` | `Omnibridge\Doctrine\Omnibase\DatabaseSubscriber\IntlSubscriber` |
| `Base\DatabaseSubscriber\InverseCollectionCacheSubscriber` | `Omnibridge\Doctrine\Omnibase\DatabaseSubscriber\InverseCollectionCacheSubscriber` |
| `Base\DatabaseSubscriber\TrackingPolicySubscriber` | `Omnibridge\Doctrine\Omnibase\DatabaseSubscriber\TrackingPolicySubscriber` |

### `Base\Database\Event\`

| 5.x | 6.0 |
|---|---|
| `Base\Database\Event\DoctrineQueryEventArgs` | `Omnibridge\Doctrine\Omnibase\Database\Event\DoctrineQueryEventArgs` |
| `Base\Database\Event\ResolveDiscriminatorEventArgs` | `Omnibridge\Doctrine\Omnibase\Database\Event\ResolveDiscriminatorEventArgs` |

### `Base\Database\Filter\`

| 5.x | 6.0 |
|---|---|
| `Base\Database\Filter\TrashFilter` | `Omnibridge\Doctrine\Omnibase\Database\Filter\TrashFilter` |
| `Base\Database\Filter\VaultFilter` | `Omnibridge\Doctrine\Omnibase\Database\Filter\VaultFilter` |

### `Base\Database\Function\`

| 5.x | 6.0 |
|---|---|
| `Base\Database\Function\Field` | `Omnibridge\Doctrine\Omnibase\Database\Function\Field` |
| `Base\Database\Function\JsonArrayPosition` | `Omnibridge\Doctrine\Omnibase\Database\Function\JsonArrayPosition` |
| `Base\Database\Function\Rand` | `Omnibridge\Doctrine\Omnibase\Database\Function\Rand` |

### `Base\Database\Mapping\`

| 5.x | 6.0 |
|---|---|
| `Base\Database\Mapping\ClassMetadataCompletor` | `Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataCompletor` |
| `Base\Database\Mapping\ClassMetadataFactory` | `Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataFactory` |
| `Base\Database\Mapping\ClassMetadataManipulator` | `Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator` |
| `Base\Database\Mapping\NamingStrategy` | `Omnibridge\Doctrine\Omnibase\Database\Mapping\NamingStrategy` |

### `Base\Database\Middleware\`

| 5.x | 6.0 |
|---|---|
| `Base\Database\Middleware\PlatformMiddleware` | `Omnibridge\Doctrine\Omnibase\Database\Middleware\PlatformMiddleware` |

### `Base\Database\Repository\`

| 5.x | 6.0 |
|---|---|
| `Base\Database\Repository\ServiceEntityParser` | `Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityParser` |
| `Base\Database\Repository\ServiceEntityRepository` | `Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository` |

### `Base\Database\Schema\`

| 5.x | 6.0 |
|---|---|
| `Base\Database\Schema\Describer` | `Omnibridge\Doctrine\Omnibase\Database\Schema\Describer` |

### `Base\Database\Type\`

| 5.x | 6.0 |
|---|---|
| `Base\Database\Type\ArrayType` | `Omnibridge\Doctrine\Omnibase\Database\Type\ArrayType` |
| `Base\Database\Type\DateTimeTypeUTC` | `Omnibridge\Doctrine\Omnibase\Database\Type\DateTimeTypeUTC` |
| `Base\Database\Type\MySQLSetType` | `Omnibridge\Doctrine\Omnibase\Database\Type\MySQLSetType` |
| `Base\Database\Type\UtcDateTimeImmutableType` | `Omnibridge\Doctrine\Omnibase\Database\Type\UtcDateTimeImmutableType` |
| `Base\Database\Type\UtcDateTimeType` | `Omnibridge\Doctrine\Omnibase\Database\Type\UtcDateTimeType` |

### `Base\Database\Type\Passkey\`

| 5.x | 6.0 |
|---|---|
| `Base\Database\Type\Passkey\Aaguid` | `Omnibridge\Doctrine\Omnibase\Database\Type\Passkey\Aaguid` |
| `Base\Database\Type\Passkey\Base64` | `Omnibridge\Doctrine\Omnibase\Database\Type\Passkey\Base64` |
| `Base\Database\Type\Passkey\TrustPath` | `Omnibridge\Doctrine\Omnibase\Database\Type\Passkey\TrustPath` |

### `Base\Database\Walker\`

| 5.x | 6.0 |
|---|---|
| `Base\Database\Walker\CountWalker` | `Omnibridge\Doctrine\Omnibase\Database\Walker\CountWalker` |
| `Base\Database\Walker\GroupByWalker` | `Omnibridge\Doctrine\Omnibase\Database\Walker\GroupByWalker` |
| `Base\Database\Walker\MysqlWalker` | `Omnibridge\Doctrine\Omnibase\Database\Walker\MysqlWalker` |
| `Base\Database\Walker\TranslatableWalker` | `Omnibridge\Doctrine\Omnibase\Database\Walker\TranslatableWalker` |

### `Base\DependencyInjection\`

| 5.x | 6.0 |
|---|---|
| `Base\DependencyInjection\BaseConfiguration` | `Omnibridge\Symfony\Omnibase\DependencyInjection\BaseConfiguration` |
| `Base\DependencyInjection\BaseExtension` | `Omnibridge\Symfony\Omnibase\DependencyInjection\BaseExtension` |

### `Base\DependencyInjection\Compiler\`

| 5.x | 6.0 |
|---|---|
| `Base\DependencyInjection\Compiler\AbstractPass` | `Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\AbstractPass` |

### `Base\DependencyInjection\Compiler\Pass\`

| 5.x | 6.0 |
|---|---|
| `Base\DependencyInjection\Compiler\Pass\AdminUrlGeneratorPass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\AdminUrlGeneratorPass` |
| `Base\DependencyInjection\Compiler\Pass\ApplicationTextsPass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\ApplicationTextsPass` |
| `Base\DependencyInjection\Compiler\Pass\AttributePass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\AttributePass` |
| `Base\DependencyInjection\Compiler\Pass\ContractsPass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\ContractsPass` |
| `Base\DependencyInjection\Compiler\Pass\DoctrineConfigurationPass` | `Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass\DoctrineConfigurationPass` |
| `Base\DependencyInjection\Compiler\Pass\EntityExtensionPass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\EntityExtensionPass` |
| `Base\DependencyInjection\Compiler\Pass\EnumerationTypesPass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\EnumerationTypesPass` |
| `Base\DependencyInjection\Compiler\Pass\FlysystemStoragesPass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\FlysystemStoragesPass` |
| `Base\DependencyInjection\Compiler\Pass\FormerFormTypesPass` | `Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass\FormerFormTypesPass` |
| `Base\DependencyInjection\Compiler\Pass\GuardPass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\GuardPass` |
| `Base\DependencyInjection\Compiler\Pass\IconProviderPass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\IconProviderPass` |
| `Base\DependencyInjection\Compiler\Pass\ObfuscatorCompressionPass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\ObfuscatorCompressionPass` |
| `Base\DependencyInjection\Compiler\Pass\RepositoryInterfacesPass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\RepositoryInterfacesPass` |
| `Base\DependencyInjection\Compiler\Pass\SharingPass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\SharingPass` |
| `Base\DependencyInjection\Compiler\Pass\TagRendererPass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\TagRendererPass` |
| `Base\DependencyInjection\Compiler\Pass\TextOverrideCachePass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\TextOverrideCachePass` |
| `Base\DependencyInjection\Compiler\Pass\TradingPass` | `Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass\TradingPass` |
| `Base\DependencyInjection\Compiler\Pass\TwigComponentPass` | `Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass\TwigComponentPass` |
| `Base\DependencyInjection\Compiler\Pass\WebauthnDeprecatedAliasPass` | `Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass\WebauthnDeprecatedAliasPass` |
| `Base\DependencyInjection\Compiler\Pass\WebauthnMappingPass` | `Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass\WebauthnMappingPass` |
| `Base\DependencyInjection\Compiler\Pass\WorkflowPass` | `Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass\WorkflowPass` |

### `Base\DependencyInjection\Dumper\`

| 5.x | 6.0 |
|---|---|
| `Base\DependencyInjection\Dumper\CliDumper` | `Omnibridge\Symfony\Omnibase\DependencyInjection\Dumper\CliDumper` |
| `Base\DependencyInjection\Dumper\HtmlDumper` | `Omnibridge\Symfony\Omnibase\DependencyInjection\Dumper\HtmlDumper` |

### `Base\EntitySubscriber\`

| 5.x | 6.0 |
|---|---|
| `Base\EntitySubscriber\ExtensionSubscriber` | `Omnibridge\Doctrine\Omnibase\EntitySubscriber\ExtensionSubscriber` |
| `Base\EntitySubscriber\SettingSubscriber` | `Omnibridge\Doctrine\Omnibase\EntitySubscriber\SettingSubscriber` |
| `Base\EntitySubscriber\TrasheableSubscriber` | `Omnibridge\Doctrine\Omnibase\EntitySubscriber\TrasheableSubscriber` |
| `Base\EntitySubscriber\VersionableSubscriber` | `Omnibridge\Doctrine\Omnibase\EntitySubscriber\VersionableSubscriber` |

### `Base\Field\Type\`

| 5.x | 6.0 |
|---|---|
| `Base\Field\Type\ArrayType` | `Omnibridge\Symfony\Omnibase\Field\Type\ArrayType` |
| `Base\Field\Type\AssociationFileType` | `Omnibridge\Symfony\Omnibase\Field\Type\AssociationFileType` |
| `Base\Field\Type\AssociationType` | `Omnibridge\Symfony\Omnibase\Field\Type\AssociationType` |
| `Base\Field\Type\AttributeType` | `Omnibridge\Symfony\Omnibase\Field\Type\AttributeType` |
| `Base\Field\Type\AudioType` | `Omnibridge\Symfony\Omnibase\Field\Type\AudioType` |
| `Base\Field\Type\AvatarType` | `Omnibridge\Symfony\Omnibase\Field\Type\AvatarType` |
| `Base\Field\Type\BooleanType` | `Omnibridge\Symfony\Omnibase\Field\Type\BooleanType` |
| `Base\Field\Type\ButtonType` | `Omnibridge\Symfony\Omnibase\Field\Type\ButtonType` |
| `Base\Field\Type\CodeType` | `Omnibridge\Symfony\Omnibase\Field\Type\CodeType` |
| `Base\Field\Type\CollectionType` | `Omnibridge\Symfony\Omnibase\Field\Type\CollectionType` |
| `Base\Field\Type\ColorPickerType` | `Omnibridge\Symfony\Omnibase\Field\Type\ColorPickerType` |
| `Base\Field\Type\ColorType` | `Omnibridge\Symfony\Omnibase\Field\Type\ColorType` |
| `Base\Field\Type\CountryType` | `Omnibridge\Symfony\Omnibase\Field\Type\CountryType` |
| `Base\Field\Type\CropperType` | `Omnibridge\Symfony\Omnibase\Field\Type\CropperType` |
| `Base\Field\Type\CurrencyType` | `Omnibridge\Symfony\Omnibase\Field\Type\CurrencyType` |
| `Base\Field\Type\DateTimePickerType` | `Omnibridge\Symfony\Omnibase\Field\Type\DateTimePickerType` |
| `Base\Field\Type\DiscriminatorType` | `Omnibridge\Symfony\Omnibase\Field\Type\DiscriminatorType` |
| `Base\Field\Type\EditorType` | `Omnibridge\Symfony\Omnibase\Field\Type\EditorType` |
| `Base\Field\Type\EmojiPickerType` | `Omnibridge\Symfony\Omnibase\Field\Type\EmojiPickerType` |
| `Base\Field\Type\FileType` | `Omnibridge\Symfony\Omnibase\Field\Type\FileType` |
| `Base\Field\Type\ForexType` | `Omnibridge\Symfony\Omnibase\Field\Type\ForexType` |
| `Base\Field\Type\GenderType` | `Omnibridge\Symfony\Omnibase\Field\Type\GenderType` |
| `Base\Field\Type\IconType` | `Omnibridge\Symfony\Omnibase\Field\Type\IconType` |
| `Base\Field\Type\ImageType` | `Omnibridge\Symfony\Omnibase\Field\Type\ImageType` |
| `Base\Field\Type\MoneyType` | `Omnibridge\Symfony\Omnibase\Field\Type\MoneyType` |
| `Base\Field\Type\NumberType` | `Omnibridge\Symfony\Omnibase\Field\Type\NumberType` |
| `Base\Field\Type\PasswordType` | `Omnibridge\Symfony\Omnibase\Field\Type\PasswordType` |
| `Base\Field\Type\QuadrantType` | `Omnibridge\Symfony\Omnibase\Field\Type\QuadrantType` |
| `Base\Field\Type\RoleType` | `Omnibridge\Symfony\Omnibase\Field\Type\RoleType` |
| `Base\Field\Type\RouteType` | `Omnibridge\Symfony\Omnibase\Field\Type\RouteType` |
| `Base\Field\Type\SelectType` | `Omnibridge\Symfony\Omnibase\Field\Type\SelectType` |
| `Base\Field\Type\SlugType` | `Omnibridge\Symfony\Omnibase\Field\Type\SlugType` |
| `Base\Field\Type\StateType` | `Omnibridge\Symfony\Omnibase\Field\Type\StateType` |
| `Base\Field\Type\StockType` | `Omnibridge\Symfony\Omnibase\Field\Type\StockType` |
| `Base\Field\Type\SubmitType` | `Omnibridge\Symfony\Omnibase\Field\Type\SubmitType` |
| `Base\Field\Type\TranslatableType` | `Omnibridge\Symfony\Omnibase\Field\Type\TranslatableType` |
| `Base\Field\Type\TranslationType` | `Omnibridge\Symfony\Omnibase\Field\Type\TranslationType` |
| `Base\Field\Type\VideoType` | `Omnibridge\Symfony\Omnibase\Field\Type\VideoType` |
| `Base\Field\Type\WysiwygType` | `Omnibridge\Symfony\Omnibase\Field\Type\WysiwygType` |

### `Base\Form\Common\`

| 5.x | 6.0 |
|---|---|
| `Base\Form\Common\AbstractType` | `Omnibridge\Symfony\Omnibase\Form\Common\AbstractType` |
| `Base\Form\Common\FormTypeInterface` | `Omnibridge\Symfony\Omnibase\Form\Common\FormTypeInterface` |

### `Base\Form\Extension\`

| 5.x | 6.0 |
|---|---|
| `Base\Form\Extension\FormTypeBootstrapExtension` | `Omnibridge\Symfony\Omnibase\Form\Extension\FormTypeBootstrapExtension` |
| `Base\Form\Extension\FormTypeCollabExtension` | `Omnibridge\Symfony\Omnibase\Form\Extension\FormTypeCollabExtension` |
| `Base\Form\Extension\FormTypeCollectionExtension` | `Omnibridge\Symfony\Omnibase\Form\Extension\FormTypeCollectionExtension` |
| `Base\Form\Extension\FormTypeCsrfExtension` | `Omnibridge\Symfony\Omnibase\Form\Extension\FormTypeCsrfExtension` |
| `Base\Form\Extension\FormTypeExtension` | `Omnibridge\Symfony\Omnibase\Form\Extension\FormTypeExtension` |
| `Base\Form\Extension\FormTypeGuardExtension` | `Omnibridge\Symfony\Omnibase\Form\Extension\FormTypeGuardExtension` |
| `Base\Form\Extension\FormTypeTranslateExtension` | `Omnibridge\Symfony\Omnibase\Form\Extension\FormTypeTranslateExtension` |
| `Base\Form\Extension\FormTypeWebpackExtension` | `Omnibridge\Symfony\Omnibase\Form\Extension\FormTypeWebpackExtension` |

### `Base\Form\`

| 5.x | 6.0 |
|---|---|
| `Base\Form\FormFactory` | `Omnibridge\Symfony\Omnibase\Form\FormFactory` |
| `Base\Form\FormFactoryInterface` | `Omnibridge\Symfony\Omnibase\Form\FormFactoryInterface` |
| `Base\Form\FormProcessor` | `Omnibridge\Symfony\Omnibase\Form\FormProcessor` |
| `Base\Form\FormProcessorInterface` | `Omnibridge\Symfony\Omnibase\Form\FormProcessorInterface` |
| `Base\Form\FormProxy` | `Omnibridge\Symfony\Omnibase\Form\FormProxy` |
| `Base\Form\FormProxyInterface` | `Omnibridge\Symfony\Omnibase\Form\FormProxyInterface` |

### `Base\Form\Traits\`

| 5.x | 6.0 |
|---|---|
| `Base\Form\Traits\FormGuessInterface` | `Omnibridge\Symfony\Omnibase\Form\Traits\FormGuessInterface` |
| `Base\Form\Traits\FormGuessTrait` | `Omnibridge\Symfony\Omnibase\Form\Traits\FormGuessTrait` |
| `Base\Form\Traits\FormProcessorTrait` | `Omnibridge\Symfony\Omnibase\Form\Traits\FormProcessorTrait` |

### `Base\Form\Transformer\`

| 5.x | 6.0 |
|---|---|
| `Base\Form\Transformer\ScaleNumberTransformer` | `Omnibridge\Symfony\Omnibase\Form\Transformer\ScaleNumberTransformer` |
| `Base\Form\Transformer\StrippedNumberToLocalizedStringTransformer` | `Omnibridge\Symfony\Omnibase\Form\Transformer\StrippedNumberToLocalizedStringTransformer` |

### `Base\Form\Type\`

| 5.x | 6.0 |
|---|---|
| `Base\Form\Type\CommentType` | `Omnibridge\Symfony\Omnibase\Form\Type\CommentType` |
| `Base\Form\Type\ContactType` | `Omnibridge\Symfony\Omnibase\Form\Type\ContactType` |
| `Base\Form\Type\LayoutSettingListType` | `Omnibridge\Symfony\Omnibase\Form\Type\LayoutSettingListType` |
| `Base\Form\Type\LayoutWidgetListType` | `Omnibridge\Symfony\Omnibase\Form\Type\LayoutWidgetListType` |
| `Base\Form\Type\Login2FAType` | `Omnibridge\Symfony\Omnibase\Form\Type\Login2FAType` |
| `Base\Form\Type\PrivacyType` | `Omnibridge\Symfony\Omnibase\Form\Type\PrivacyType` |
| `Base\Form\Type\SecurityLoginTokenType` | `Omnibridge\Symfony\Omnibase\Form\Type\SecurityLoginTokenType` |
| `Base\Form\Type\SecurityLoginTwoFactorType` | `Omnibridge\Symfony\Omnibase\Form\Type\SecurityLoginTwoFactorType` |
| `Base\Form\Type\SecurityLoginType` | `Omnibridge\Symfony\Omnibase\Form\Type\SecurityLoginType` |
| `Base\Form\Type\SecurityRegistrationType` | `Omnibridge\Symfony\Omnibase\Form\Type\SecurityRegistrationType` |
| `Base\Form\Type\SecurityResetPasswordConfirmType` | `Omnibridge\Symfony\Omnibase\Form\Type\SecurityResetPasswordConfirmType` |
| `Base\Form\Type\SecurityResetPasswordType` | `Omnibridge\Symfony\Omnibase\Form\Type\SecurityResetPasswordType` |
| `Base\Form\Type\ThreadSearchType` | `Omnibridge\Symfony\Omnibase\Form\Type\ThreadSearchType` |
| `Base\Form\Type\ThreadSearchbarType` | `Omnibridge\Symfony\Omnibase\Form\Type\ThreadSearchbarType` |
| `Base\Form\Type\UserProfileType` | `Omnibridge\Symfony\Omnibase\Form\Type\UserProfileType` |
| `Base\Form\Type\UserSearchType` | `Omnibridge\Symfony\Omnibase\Form\Type\UserSearchType` |
| `Base\Form\Type\UserSettingsType` | `Omnibridge\Symfony\Omnibase\Form\Type\UserSettingsType` |

### `Base\Inspector\`

| 5.x | 6.0 |
|---|---|
| `Base\Inspector\DoctrineDataCollector` | `Omnibridge\Symfony\Omnibase\Inspector\DoctrineDataCollector` |
| `Base\Inspector\LocalizerDataCollector` | `Omnibridge\Symfony\Omnibase\Inspector\LocalizerDataCollector` |

### `Base\Repository\Analytics\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\Analytics\PageViewRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Analytics\PageViewRepository` |
| `Base\Repository\Analytics\PageVisitRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Analytics\PageVisitRepository` |
| `Base\Repository\Analytics\VisitRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Analytics\VisitRepository` |

### `Base\Repository\Extension\Abstract\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\Extension\Abstract\AbstractExtensionRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Extension\Abstract\AbstractExtensionRepository` |

### `Base\Repository\Extension\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\Extension\LogRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Extension\LogRepository` |
| `Base\Repository\Extension\OrderingRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Extension\OrderingRepository` |
| `Base\Repository\Extension\RevisionRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Extension\RevisionRepository` |
| `Base\Repository\Extension\TrashBallRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Extension\TrashBallRepository` |

### `Base\Repository\Hours\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\Hours\ScopedWeekRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Hours\ScopedWeekRepository` |
| `Base\Repository\Hours\SpecialDayRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Hours\SpecialDayRepository` |
| `Base\Repository\Hours\WeekDayHoursRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Hours\WeekDayHoursRepository` |

### `Base\Repository\Layout\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\Layout\AttributeRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\AttributeRepository` |
| `Base\Repository\Layout\ImageCropRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\ImageCropRepository` |
| `Base\Repository\Layout\ImageRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\ImageRepository` |
| `Base\Repository\Layout\RedirectionRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\RedirectionRepository` |
| `Base\Repository\Layout\SemanticRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\SemanticRepository` |
| `Base\Repository\Layout\SettingRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\SettingRepository` |
| `Base\Repository\Layout\ShortLinkRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\ShortLinkRepository` |
| `Base\Repository\Layout\TextOverrideRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\TextOverrideRepository` |
| `Base\Repository\Layout\TooltipRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\TooltipRepository` |
| `Base\Repository\Layout\WidgetRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\WidgetRepository` |

### `Base\Repository\Layout\Attribute\Adapter\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\Layout\Attribute\Adapter\ArrayAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\ArrayAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\ColorAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\ColorAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\CountryAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\CountryAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\DateRangeAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\DateRangeAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\DateTimeAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\DateTimeAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\HyperpatternAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\HyperpatternAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\ImageAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\ImageAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\MoneyAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\MoneyAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\NumberAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\NumberAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\PercentAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\PercentAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\ScalarAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\ScalarAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\ScoreAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\ScoreAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\TextAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\TextAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\TextareaAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\TextareaAdapterRepository` |

### `Base\Repository\Layout\Attribute\Adapter\Common\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\Layout\Attribute\Adapter\Common\AbstractActionAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\Common\AbstractActionAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\Common\AbstractRuleAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\Common\AbstractRuleAdapterRepository` |
| `Base\Repository\Layout\Attribute\Adapter\Common\AbstractScopeAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Adapter\Common\AbstractScopeAdapterRepository` |

### `Base\Repository\Layout\Attribute\Common\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\Layout\Attribute\Common\AbstractActionRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Common\AbstractActionRepository` |
| `Base\Repository\Layout\Attribute\Common\AbstractAttributeRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Common\AbstractAttributeRepository` |
| `Base\Repository\Layout\Attribute\Common\AbstractRuleRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Common\AbstractRuleRepository` |
| `Base\Repository\Layout\Attribute\Common\AbstractScopeRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\Common\AbstractScopeRepository` |

### `Base\Repository\Layout\Attribute\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\Layout\Attribute\HyperlinkRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Attribute\HyperlinkRepository` |

### `Base\Repository\Layout\Widget\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\Layout\Widget\AttachmentRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Widget\AttachmentRepository` |
| `Base\Repository\Layout\Widget\LinkRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Widget\LinkRepository` |
| `Base\Repository\Layout\Widget\PageRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Widget\PageRepository` |
| `Base\Repository\Layout\Widget\RouteRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Widget\RouteRepository` |
| `Base\Repository\Layout\Widget\SlotRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Widget\SlotRepository` |

### `Base\Repository\Layout\Widget\Set\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\Layout\Widget\Set\BookRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Widget\Set\BookRepository` |
| `Base\Repository\Layout\Widget\Set\LinksetRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Widget\Set\LinksetRepository` |
| `Base\Repository\Layout\Widget\Set\MenuRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Widget\Set\MenuRepository` |
| `Base\Repository\Layout\Widget\Set\NetworkRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Widget\Set\NetworkRepository` |
| `Base\Repository\Layout\Widget\Set\PaperclipRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Layout\Widget\Set\PaperclipRepository` |

### `Base\Repository\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\ThreadIntlRepository` | `Omnibridge\Doctrine\Omnibase\Repository\ThreadIntlRepository` |
| `Base\Repository\ThreadRepository` | `Omnibridge\Doctrine\Omnibase\Repository\ThreadRepository` |
| `Base\Repository\UserRepository` | `Omnibridge\Doctrine\Omnibase\Repository\UserRepository` |

### `Base\Repository\Thread\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\Thread\CommentRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Thread\CommentRepository` |
| `Base\Repository\Thread\LikeRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Thread\LikeRepository` |
| `Base\Repository\Thread\MentionRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Thread\MentionRepository` |
| `Base\Repository\Thread\TagRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Thread\TagRepository` |
| `Base\Repository\Thread\TaxonRepository` | `Omnibridge\Doctrine\Omnibase\Repository\Thread\TaxonRepository` |

### `Base\Repository\User\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\User\AddressRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\AddressRepository` |
| `Base\Repository\User\ComplaintRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\ComplaintRepository` |
| `Base\Repository\User\ConnectionRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\ConnectionRepository` |
| `Base\Repository\User\GroupRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\GroupRepository` |
| `Base\Repository\User\IdentityRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\IdentityRepository` |
| `Base\Repository\User\NotificationRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\NotificationRepository` |
| `Base\Repository\User\PasskeyRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\PasskeyRepository` |
| `Base\Repository\User\PenaltyRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\PenaltyRepository` |
| `Base\Repository\User\PermissionRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\PermissionRepository` |
| `Base\Repository\User\PushSubscriptionRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\PushSubscriptionRepository` |
| `Base\Repository\User\SanctionRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\SanctionRepository` |
| `Base\Repository\User\TokenRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\TokenRepository` |

### `Base\Repository\User\Attribute\Action\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\User\Attribute\Action\GrantPermissionAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\Attribute\Action\GrantPermissionAdapterRepository` |
| `Base\Repository\User\Attribute\Action\GrantRoleAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\Attribute\Action\GrantRoleAdapterRepository` |

### `Base\Repository\User\Attribute\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\User\Attribute\GroupActionRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\Attribute\GroupActionRepository` |
| `Base\Repository\User\Attribute\GroupRuleRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\Attribute\GroupRuleRepository` |
| `Base\Repository\User\Attribute\GroupScopeRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\Attribute\GroupScopeRepository` |

### `Base\Repository\User\Attribute\Scope\`

| 5.x | 6.0 |
|---|---|
| `Base\Repository\User\Attribute\Scope\GroupAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\Attribute\Scope\GroupAdapterRepository` |
| `Base\Repository\User\Attribute\Scope\UserAdapterRepository` | `Omnibridge\Doctrine\Omnibase\Repository\User\Attribute\Scope\UserAdapterRepository` |

### `Base\Response\`

| 5.x | 6.0 |
|---|---|
| `Base\Response\PdfResponse` | `Omnibridge\Symfony\Omnibase\Response\PdfResponse` |
| `Base\Response\XmlResponse` | `Omnibridge\Symfony\Omnibase\Response\XmlResponse` |

### `Base\Routing\`

| 5.x | 6.0 |
|---|---|
| `Base\Routing\AdvancedRouter` | `Omnibridge\Symfony\Omnibase\Routing\AdvancedRouter` |
| `Base\Routing\AdvancedRouterInterface` | `Omnibridge\Symfony\Omnibase\Routing\AdvancedRouterInterface` |

### `Base\Routing\Generator\`

| 5.x | 6.0 |
|---|---|
| `Base\Routing\Generator\AdvancedUrlGenerator` | `Omnibridge\Symfony\Omnibase\Routing\Generator\AdvancedUrlGenerator` |

### `Base\Routing\Loader\`

| 5.x | 6.0 |
|---|---|
| `Base\Routing\Loader\RouteTranslationsResourceLoader` | `Omnibridge\Symfony\Omnibase\Routing\Loader\RouteTranslationsResourceLoader` |

### `Base\Routing\Matcher\`

| 5.x | 6.0 |
|---|---|
| `Base\Routing\Matcher\AdvancedUrlMatcher` | `Omnibridge\Symfony\Omnibase\Routing\Matcher\AdvancedUrlMatcher` |

### `Base\Security\`

| 5.x | 6.0 |
|---|---|
| `Base\Security\AccessTokenHandler` | `Omnibridge\Symfony\Omnibase\Security\AccessTokenHandler` |
| `Base\Security\LoginFormAuthenticator` | `Omnibridge\Symfony\Omnibase\Security\LoginFormAuthenticator` |
| `Base\Security\OAuthUserProvider` | `Omnibridge\Symfony\Omnibase\Security\OAuthUserProvider` |
| `Base\Security\RescueFormAuthenticator` | `Omnibridge\Symfony\Omnibase\Security\RescueFormAuthenticator` |
| `Base\Security\SignInGuard` | `Omnibridge\Symfony\Omnibase\Security\SignInGuard` |
| `Base\Security\UserChecker` | `Omnibridge\Symfony\Omnibase\Security\UserChecker` |
| `Base\Security\UserProvider` | `Omnibridge\Symfony\Omnibase\Security\UserProvider` |

### `Base\Security\Session\`

| 5.x | 6.0 |
|---|---|
| `Base\Security\Session\DynamicSessionStorage` | `Omnibridge\Symfony\Omnibase\Security\Session\DynamicSessionStorage` |
| `Base\Security\Session\DynamicSessionStorageFactory` | `Omnibridge\Symfony\Omnibase\Security\Session\DynamicSessionStorageFactory` |

### `Base\Security\Voter\`

| 5.x | 6.0 |
|---|---|
| `Base\Security\Voter\AccessVoter` | `Omnibridge\Symfony\Omnibase\Security\Voter\AccessVoter` |
| `Base\Security\Voter\AdminVoter` | `Omnibridge\Symfony\Omnibase\Security\Voter\AdminVoter` |
| `Base\Security\Voter\ComplaintVoter` | `Omnibridge\Symfony\Omnibase\Security\Voter\ComplaintVoter` |
| `Base\Security\Voter\PermissionVoter` | `Omnibridge\Symfony\Omnibase\Security\Voter\PermissionVoter` |

### `Base\Service\`

| 5.x | 6.0 |
|---|---|
| `Base\Service\HotParameterBag` | `Omnibridge\Symfony\Omnibase\Service\HotParameterBag` |
| `Base\Service\ParameterBag` | `Omnibridge\Symfony\Omnibase\Service\ParameterBag` |

### `Base\Subscriber\`

| 5.x | 6.0 |
|---|---|
| `Base\Subscriber\AnalyticsSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\AnalyticsSubscriber` |
| `Base\Subscriber\DemoBackOfficeSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\DemoBackOfficeSubscriber` |
| `Base\Subscriber\DemoSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\DemoSubscriber` |
| `Base\Subscriber\EntityDispatcherFlushSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\EntityDispatcherFlushSubscriber` |
| `Base\Subscriber\FlashBagSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\FlashBagSubscriber` |
| `Base\Subscriber\HotParameterBagSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\HotParameterBagSubscriber` |
| `Base\Subscriber\IntegritySubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\IntegritySubscriber` |
| `Base\Subscriber\IsGrantedSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\IsGrantedSubscriber` |
| `Base\Subscriber\LocalizerSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\LocalizerSubscriber` |
| `Base\Subscriber\MailTrapSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\MailTrapSubscriber` |
| `Base\Subscriber\NewDeviceSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\NewDeviceSubscriber` |
| `Base\Subscriber\NotifierSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\NotifierSubscriber` |
| `Base\Subscriber\PageViewSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\PageViewSubscriber` |
| `Base\Subscriber\PasswordChangeSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\PasswordChangeSubscriber` |
| `Base\Subscriber\ProfilerSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\ProfilerSubscriber` |
| `Base\Subscriber\RedirectionSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\RedirectionSubscriber` |
| `Base\Subscriber\ReferrerSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\ReferrerSubscriber` |
| `Base\Subscriber\RequestScopedStateSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\RequestScopedStateSubscriber` |
| `Base\Subscriber\RouterSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\RouterSubscriber` |
| `Base\Subscriber\SecondLevelCacheConsoleSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\SecondLevelCacheConsoleSubscriber` |
| `Base\Subscriber\SecurityEnrolmentSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\SecurityEnrolmentSubscriber` |
| `Base\Subscriber\SecuritySubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\SecuritySubscriber` |
| `Base\Subscriber\TemplatedEmailSubjectSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\TemplatedEmailSubjectSubscriber` |
| `Base\Subscriber\TwigSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\TwigSubscriber` |
| `Base\Subscriber\ValidCacheSubscriber` | `Omnibridge\Symfony\Omnibase\Subscriber\ValidCacheSubscriber` |
