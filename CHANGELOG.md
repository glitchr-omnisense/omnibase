# Changelog

All notable changes to this bundle are documented here, per release tag.
Versions follow the branch-per-major scheme: branch `3.x` → tags `3.0.0`,
`3.1.0`, … (the next major continues on `4.x`).

## [4.0] — branch `4.x`, unreleased

The core requires its Foundation alone (plan "omnibase agnostique", step C7; decisions D7 and
D11): what `tests/Foundation.php` loads and what it stands on. 3.x stays as it was, for the
applications still locked on it. See [docs/20-architecture/packages.md](docs/20-architecture/packages.md).

### Added: the editors on every host (plan "omnibase agnostic", step F5d, slice 6, 2026-11-03)

- **`Base\Field\Behaviour\Wysiwyg`** (`wysiwyg`, Quill) and **`Base\Field\Behaviour\Editor`** (`editor`, Editor.js): what
  omnibridge/symfony's `WysiwygType` and `EditorType` decided, which they now ask - their options, the widget's id (dashes made
  underscores), Quill's settings, Editor.js's (the collaboration's room when one is asked: `editor_record`, `editor_field`,
  `editor_locale`), the value with its pictures' addresses, the routes' addresses; `reverse()` reads a document without a block
  as no text (EditorType's PRE_SUBMIT). Registered in `Behaviours`; `FormView` gives an editor its record and its field's name,
  inside a translations field too.
- **`Base\Field\Editor\EditorJs`**: what `EditorController` answered under Symfony, moved here as it was - `ROUTES` (the seven
  `ux_editorjs_*`), `data()`/`addresses()`, the mentions `users()`/`keywords()`/`threads()` (a site's own `$fields` still taken),
  `uploadByFile()`/`uploadByUrl()`, `ticket()`, `autosave()` (on the unit of work and the entities' shapes, no longer Doctrine's
  entity manager), `roomToken()`, `author()`, `picture()`. In `Base\Services` and the Symfony runtime's contracts;
  `EditorController` delegates (its constructor takes the service; `App\Controller\UX\EditorController` overrides keep working).
- The conformance samples of `editor` and `wysiwyg` carry values: a document shown, an empty one bound as null; an HTML text.
- Fixed: `Flysystem::getPublic()` no longer fails where the host has no public folder (a package's tests): nothing is reachable
  from it.
- `tests/Field/EditorTest`; `EditorTypeCollabRelayTest` hands the type its `EditorJs`; `ParameterBagServicesTest` reads the
  paginator's bag (the controller has none any more). `tests/Agnostic.php` 738, `tests/Foundation.php` 801.

### Added: records made from uploads, and the file sent refused when the form is validated, on every host (plan "omnibase agnostic", step F5d, slice 5, 2026-11-03)

- **`Base\Field\Behaviour\AssociationFile`** (the `association_file` kind: a gallery's photos, a photo's picture): what
  omnibridge/symfony's `AssociationFileType` decided, which it now asks - `DEFAULTS`, `options()` (its normalizers: the class and
  `entity_file` required, `form_type` an upload's, its former or new class name or a variant's name), `variant()`/`kind()`,
  `child()` and `path()` (the upload field's name, the file's path in a related record), `files()` (the related records' public
  files), `entries()` (each stored file's name by its record's id: `data-entry-id`), `childOptions()`, `records()` (what is sent
  made into the related records: a file sent a new record given `entity_data`, a stored file's name its record kept) - and, for
  the neutral side, `bind()` (the records written into the record: a to-many collection replaced, an inverse side's records told
  their owner, from the entity's shape), `required()`, `isToMany()`. `FormView` draws it as the type's compound (widget
  `associationfile`, its upload field inside, `entityId`); `FormBinder` binds it (the entity hydrator from the host's runtime);
  `Guess` reads whether it holds several and in order from the shape.
- **`Upload::refusal()`**: `FileType`'s `File` constraint on the file sent (`[raw]`), on every host - a type its `mime_types` do
  not match (`file.mime_type`), else a size over its ceiling (`file.max_size`), with the constraint's message and parameters.
  The neutral `FormBinder` refuses such a file as a violation of the field (an `association_file`'s: of its upload field), the
  data untouched, so a host without Symfony's forms refuses it when the form is validated, no longer later at the flush.
- `BaseTrait::isEntity()` answers from the unit of work's shapes where the host gives no Doctrine manipulator (omnibridge/laravel:
  a trashed record's entry, `TrashRecorder`, asked it and failed).
- tests/Field/AssociationFileTest.
- The harness's image (docker/Dockerfile) requires the Twig extras the core's templates use (`twig/cache-extra`, `intl-extra`,
  `inky-extra`, `cssinliner-extra`), as a Twig site does: an image built afresh had none since F4.

### Fixed

- `Base\Validator\Constraints\File` reads an integer as bytes: `FileType` gives its ceiling so (`Uploader::getMaxFilesize()`),
  which the constraint read as a string of bits - a field announcing 32 MB refused a file over 4 MB. A size written out
  (`#[File(max_size: "32MB")]`) is read as before.

### Added: the uploads on every host (plan "omnibase agnostic", step F5d, slice 4, 2026-11-03)

- **`Base\Field\Behaviour\Upload`** (the behaviour of `file`, `image`, `avatar`, `audio` and `video`, one variant each): what
  omnibridge/symfony's `FileType`, `ImageType`, `AvatarType`, `AudioType` and `VideoType` decided, which they now ask -
  `DEFAULTS` and `VARIANTS` (their defaults; `FileType`'s `sortable-js` stays the host's), `options()` (their normalizers: the
  class the data class, a sound's or a film's several sources, the clipboard, an avatar's square cropper), `vars()` (what their
  blocks read of the record's files: the stored files' names, public addresses, thumbnails, previews, download links, the largest
  size and the types accepted from the record's `#[Uploader]` and `#[File]`, the dropzone's settings and its address, an image's
  thumbnail, modal and cropper), `endpoints()` (`ux_dropzone`), `reverse()` (what a form sends back, read as `FileType` reads
  it: the file sent, else the one fetched, else what `file` names - a dropzone's kept files, a stored file's name -; nothing is
  null). The record's `#[Uploader]` stores the file when it is flushed, as before.
- **`Base\Field\Upload\Dropzone`** (in `Base\Services`' list and the Symfony runtime's contracts): what `DropzoneController`
  answered, moved as it was - `ROUTES` (`ux_dropzone`, `_preview`, `_delete`), `data()` (the settings and the visitor's token of
  intent `dropzone`, obfuscated), `store()` (a file kept under a new uuid in the cache folder's `dropzone/`, or refused in the
  fields' words), `preview()`, `delete()`, `files()` (the uuids a form sends back as the files to store). `DropzoneController`
  delegates to it; omnibridge/laravel's `UploadController` asks it the same way.
- `FormView::of()` takes the data the form edits (`FormProcessor::createView()` and `FormSubmission::createView()` give it): a
  file field's view carries the Upload behaviour's vars for the record. `FormBinder`: a file field's compound sent empty writes
  null (its file removed, as `FileType` reads it); a field not sent keeps the data's file, as before.
- `tests/Field/UploadTest`. tests/Agnostic.php 734, tests/Foundation.php 797 (+2). Docs: docs/20-architecture/forms.md.

### Fixed: a record's picker starts with its records selected (plan "omnibase agnostic", step F5d, 2026-11-03)

- omnibridge/symfony's `SelectType` told select2 to select the records themselves, which JSON printed as empty objects
  (`[{}]`), and form-type-select2.js's `$(field).val()` selected nothing; it tells their ids now, the values of their entries,
  as omnibridge/laravel's renderer does. `tests/Field/SelectTypeSelectedTest`.

### Added: a translations field on every host (plan "omnibase agnostic", step F5d, slice 3, 2026-11-03)

- **`Base\Field\Behaviour\Translation`**: what omnibase's `TranslationType` decides, which it now asks - `options()` (its
  defaults through the `Locales` contract), `requiredLocales()`, `locales()` (the languages drawn; a single-language field draws
  the default one, or the record's one language with the default's fields), `entryRequired()` (the default and the required
  languages), `perLocale()` (`locale_options`, `display: false`), `required()` (a callable `required` checked and called); and
  for the hosts without Symfony's forms, `fields()` (each language's descriptors), `read()` (the record's translations as each
  language's values), `bind()` (what is sent written into the record's translations through `translate()`, an empty one not
  kept).
- **`Base\Field\Behaviour\RecordFields`**: a record's fields as an embedding form lists them, what
  `ClassMetadataManipulator::getFields()` answers from Doctrine read from the entity's shape - the fields named first, then the
  other columns, the identifier, the translation's record and language and the excluded ones left out, each with the kind its
  column says (a string a text, a text a textarea, a list a select of free tags, an integer a number, a date and time a
  datetime picker, an enumeration a select); `descriptor()` (a field as `AssociationType` adds it: not required where its column
  may be empty), `kindOf()` (a host type's name read as the kind it renders).
- **`FormView`**: a translations field has one part per language, each its translation entity's fields with the record's values,
  named and identified as Symfony names them (`form[translations][fr-FR][title]`), required where the field, its part and the
  translations field are; its vars are `TranslationType`'s (`single_locale`, `default_locale`, `available_locales`,
  `required_locales`, `nonempty_locales`, `compatible_locale`, `translations_empty`). **`FormBinder`** reads and binds it.
  **`Guess`**: a translations field's record is the form's; a select over a list column (json, array) holds several
  (`FormGuessTrait::guessMultiple()`).
- `tests/Field/TranslationTest` (RecordFields against `getFields()` on a thread's translations; a thread's translations drawn
  and bound). tests/Agnostic.php 732, tests/Foundation.php 795 (+2). Docs: docs/20-architecture/forms.md (translations).

### Added: a select's remote search on every host (plan "omnibase agnostic", step F5d, slice 2, 2026-11-03)

- **`Base\Field\Search\RemoteSearch`** (in `Base\Services`' list) and **`Answer`**: what `AutocompleteController` answered under
  Symfony, moved here as it was - `records()` (ux_autocomplete: a class's records, paged, named, filtered by the term; an
  enumeration's values), `icons()` (ux_autocomplete_icons: an icon adapter's icons), `data()` (the payload a select hands its
  search, with the visitor's token of intent `select2` through the `Tokens` contract), `filters()`, `ROUTES`. Every check is
  kept: the token, the payload, the method (GET and POST in debug, POST otherwise). `AutocompleteController::Main()` and
  `Icons()` delegate to it (their routes, names and answers unchanged; the forex rates stay the controller's); omnibridge/symfony's
  `SelectType` makes its payload with `data()`; omnibridge/laravel answers the same routes. In the Symfony runtime's contracts.
- **`FormView`**: a select searched remotely gets its search's `data` (its address on the host, null without the route or the
  search) and starts with the entries chosen, as `SelectType` gives them - a record found by its id and named by `Autocomplete`
  (its face where the field asks for one), an icon named by its adapter's list; a select's `required` is the field's word
  (select2's `allowClear`).
- `Select`: `autocomplete_type` follows the environment (GET in debug, POST otherwise), as `SelectType`'s. `Icon`: the adapter
  defaults to `base.icon_provider.default_adapter` (Font Awesome's), an icon chosen is named as `IconType` names it
  (`Icon::entry()`). `Base\Service\Icons::adapter()`. `Autocomplete::resolveArray()` is static (it reads no translator).
- Fixed: an enumeration's search filtered the parts of each entry instead of the entries and failed (a TypeError) - each value
  whose word holds the term is given. `Translator::getEntityNouns()` outside Symfony's kernel reads the host's `Nouns` namespaces
  (it asked BaseBundle, which a Laravel host has not).
- `tests/Field/RemoteSearchTest`. tests/Agnostic.php 730, tests/Foundation.php 793 (+2). Docs: docs/20-architecture/forms.md
  (the remote search).

### Added: every field kind on every host - behaviours, coverage, conformance (plan "omnibase agnostic", step F5d, slice 1, 2026-11-03)

- **`Base\Field\Behaviour\*`**, the neutral behaviours of the rich kinds (omnisense/omnibridge's `Omnibridge\Form\FieldBehaviour`):
  `DateTimePicker`, `Slug`, `Toggle` (boolean), `JsColor` (color), `ColorPickr` (color_picker), `Number`, `Password`, `Select`
  (choice, role, state, gender, currency; `CountrySelect`; `Icon`), `ChoiceList` (locale), `Choices` (an enum's, the countries',
  the locales'), `Behaviours::of()` / `ofField()` / `complete()` / `register()`. The logic their Symfony types held - options and
  defaults, view vars, the shown and read values, select2's settings (`Select::select2()`) - is theirs; omnibridge/symfony's types
  read it, omnibridge/laravel's renderer too.
- **`FormBinder` and `FormView` read through them**: a behaviour first, the kind's generic rule after; a rich field's view carries its
  `widget`, `webpack_entry`, settings and `endpoints` (each route's address on this host, null without it); a slug's `target_id`;
  select2's entries in `SelectType`'s shape, their labels translated. A field whose host type is named has no behaviour.
- **Coverage per host**: `Kinds::ADAPTERS` names Laravel's map too (`Omnibridge\Laravel\Form\LaravelKinds`); `Kinds::coverage()`,
  `declaration()`, `declare()`, `known()`, `faults()`; `Kind::all()`.
- **`Base\Field\Conformance\{Host, Outcome, Sample, Samples, Suite}`**: the field conformance suite, the same on every host (one
  Symfony-valued sample per kind; a kind without a sample, or a host's word, fails). `tests/Field/SymfonyConformanceTest` (56 kinds,
  the harness), `BehavioursTest`.
- **`Base\Field\Behaviour\Guess`**: what a field leaves out and the record's mapping says, read on the unit of work's entity
  shapes (aliases resolved), as the Symfony types guess it from Doctrine - a select's class and multiplicity, its sorting
  (`#[OrderColumn]`), a slug's `required`. `src/Resources/field-kinds.json`: `Kind::all()` as JSON, for a host without PHP.
- tests/Agnostic.php 728, tests/Foundation.php 791 (+20 each).

### Fixed (step F5d)

- `FormBinder` reads a date, a date and time and a time as Symfony does: what is not typed is zero (midnight, no seconds), not
  the clock's (`'!'` formats). A `datetime_picker` is shown and read in its ICU `format` (2026-11-03 10:30), as `DateTimePickerType`.
- `FormBinder` reads a collection of records (a translations' or an association's field) item by item, and a value the unit of
  work does not map by its `getId()`; `Properties` reads a property through the method named after it (`isApproved()`), as
  PropertyAccess does.
- `FormView`: a `repeated` field's view says `repeated` (a renderer drew it as one text input), a submit or a button given as a field
  is omnibase's button2, the markup of a `html` field is in its vars.

### Changed: BaseService neutral, EntityHydrator on the unit of work and the shapes, the configuration reading Base\Services (plan "omnibase agnostic", step F5c, item 3, 2026-11-03)

- **`Base\Service\BaseService` is framework-neutral**: its constructor takes `?Environment`, `?ParameterBagInterface`,
  the project's folder and the start time, nothing of a framework; every method asks omnisense/omnibridge's contracts -
  the session and route through `Requests` (`addSession()`, `getSession($name)`, `removeSession()`, `getCurrentRouteName()`),
  `Urls` (`generateUrl()`, `getAsset()`), `Environment` (`isDebug()`, `isDevelopment()`, `getSecret()`), `Access`
  (`getUser()`, `isGranted()`), `Tokens` (`isCsrfTokenValid()` with a token), `UnitOfWork` (`inDoctrine()`), the entity
  hydrator (`getOriginalEntityData()`, `getOriginalEntity()`); `getParameter()`/`hasParameter()` read the parameters.
  Listed in `Base\Services` (`base.service`), so Laravel and `Omnibridge\Container` build it too.
- **Its Symfony half is omnibridge/symfony's `Omnibridge\Symfony\Omnibase\Service\SymfonyBaseService`**, which the
  container hands out as `base.service` and `Base\Service\BaseService` (lazy, the same 23 arguments): the kernel and its
  container (`getContainer()`, `getAvailableServices()`, `getParameter()`...), `exec()`, `createForm()`, the profiler,
  Symfony's request, routes and responses (`getRequest()`, `getRoute()`, `redirect()`, `redirectToRoute()`, `refresh()`,
  `isAdmin()`...), `getToken()`, `isCsrfTokenValid()` with a form, the session object, and Symfony's services as the fallback
  where no contract answers - every method an application called is kept.
- **`EntityHydrator` on omnisense/omnibridge's `UnitOfWork` and `EntityShapes`**, no Doctrine metadata: the mapping is read
  from the shapes and `Base\Database\Mapping\EntityFields` (the names - aliases from `#[Alias]`, fields by column,
  associations -, `resolve()`, `target()`, `hasAssociation()`: what ClassMetadataManipulator's getFieldNames(),
  resolveFieldPath(), fetchEntityName() answered); references through `UnitOfWork::reference()`, associations by id, uuid or
  slug through `UnitOfWorks::repositories()`, the original values through omnibridge's new `Persistence\Snapshots`
  (refused aloud, `UnsupportedBehaviourException`, by a unit of work without), which types hold an array through the new
  backend seam `Base\Database\Backend\ColumnTypes` (Doctrine's: a JsonType or a set enumeration, omnibridge/doctrine's
  DoctrineColumnTypes; without one: `json` or a SetType). Lifecycle events: omnibridge's (`EntityEvent`, `PreUpdate`'s changes)
  and Doctrine's. The property accessor is made when first needed. Doctrine's entity manager and ClassMetadataManipulator
  are still accepted, deprecated until 6.0. Listed in `Base\Services` (`base.database.entity_hydrator`).
- **config/services*.php read `Base\Services`**: `services-list.php` is loaded first and defines each listed service from
  the list; the configuration says only what Symfony adds, through `$services->get()` (tags, decoration, SymfonyBaseService's
  class, the framework's services given in place of what the list autowires); the list's contracts are aliased last
  (`services-list-contracts.php`). The compiled container is the same, definition for definition and alias for alias
  (`debug:container --format=json` before and after, in the harness).
- Tests: tests/Database/Entity/EntityHydratorTest (a Description and a unit of work of plain PHP; on Doctrine every entity's
  names, paths, targets, associations and array types as the manipulator answered them), tests/Services/container-without-framework.php
  (BareTest: BaseService and the hydrator from the list in plain PHP), tests/Contract/basetrait-seams-without-framework.php
  (the facade's session, route, link, asset and user on the contracts), BaseServiceLazyTest (the ghost is SymfonyBaseService's).

### Fixed: eight of Bridge\Symfony's classes back in the core (plan "omnibase agnostic", step F5c, item 3, slice 5 fix, 2026-11-03)

- `Base\Bridge\Symfony\{NotificationMessages, AccountScreens, SecurityUser, SecurityUserLoader, SecurityUserProvider,
  TwoFactorUser, PasskeyRecords, PasskeyUsers}` are the core's again, as they were before slice 5: they stand on Symfony's
  components (security-core, mime, http-foundation), not on its framework, and the core reaches them under any host -
  `Entity\User\Notification` calls NotificationMessages, the contracts `SecurityUser`, `TwoFactorUser`, the passkeys' and the
  user loader's alias them where security-core is installed, omnibridge/laravel renders the account pages with
  AccountScreens. Under Laravel (lesringostars-laravel) slice 5 made a notification's rendering fail ("Class
  Base\Bridge\Symfony\NotificationMessages not found"). FormerNamesTest 325. The other twelve stay omnibridge/symfony's.

### Moved: Bridge\Symfony to omnibridge/symfony (plan "omnibase agnostic", step F5c, item 3, slice 5, 2026-11-03)

- The 20 Symfony adapters of `Base\Bridge\Symfony\*` (the contracts' adapters - `LocalizerLocales`, `MediaPictures`,
  `NotificationMessages`, `RouterRoutes`, `ServiceMedia`, `WysiwygTexts`, `FlysystemFiles`, the passkey and security-user
  adapters -, `ScreenResponder`, `SymfonyScreenContext`, `AccountScreens`, `ActionArguments`, `ActionResults`,
  `AuthListeners`, `SettingBagWarmer`) are `Omnibridge\Symfony\Omnibase\*` by the F3 rule (`Base\Bridge\Symfony\X` is
  `Omnibridge\Symfony\Omnibase\X`), as they were; former names aliased (FormerNamesTest 333). `ScreenResponder::legacy()`
  finds the core's templates beside BaseBundle.
- The Symfony controllers (`Controller\*` but `Controller\Action\*` and `SignatureController`), `DependencyInjection\*`,
  `Bundle\*` and `BaseBundle` stay until step F6: the applications import the core's routes as `@BaseBundle/Controller`
  and register `Base\BaseBundle`, whose path is the core's; they move with the applications' change of those two lines.

### Moved: Symfony Security's classes to omnibridge/symfony (plan "omnibase agnostic", step F5c, item 3, slice 4, 2026-11-03)

- 13 classes, as they were, to `Omnibridge\Symfony\Omnibase\Security\`: `AccessTokenHandler`, `LoginFormAuthenticator`,
  `RescueFormAuthenticator`, `UserProvider`, `OAuthUserProvider`, `UserChecker`, `SignInGuard`,
  `Session\{DynamicSessionStorage, DynamicSessionStorageFactory}`, `Voter\{AccessVoter, AdminVoter, ComplaintVoter,
  PermissionVoter}`. Former names aliased (FormerNamesTest 313): security.yaml's authenticators, providers and checker, and
  the services' ids, keep their names.
- Stay, framework-free: `ConnectionTracker`, `CookieAnswer`, `LoginRestrictionInterface`, `SignInCaptcha`, `SignOut`,
  `UserTracker`, `WebauthnUserEntityRepository`.

### Moved: the kernel subscribers, the router, the warmers, the collectors and the responses to omnibridge/symfony (plan "omnibase agnostic", step F5c, item 3, slice 3, 2026-11-03)

- 38 classes, as they were, to `Omnibridge\Symfony\Omnibase\` by the same rule: the 21 `Subscriber\*`;
  `Routing\{AdvancedRouter, AdvancedRouterInterface, Generator\AdvancedUrlGenerator, Matcher\AdvancedUrlMatcher,
  Loader\RouteTranslationsResourceLoader}`; `Cache\Warmer\*` (6), `Cache\Clearer\CodeCachesClearer`,
  `Cache\Abstract\AbstractLocalCacheWarmer`; `Inspector\{LocalizerDataCollector, DoctrineDataCollector}`;
  `Response\{PdfResponse, XmlResponse}`. Former names aliased (FormerNamesTest 300); the service ids are unchanged.
- Stay, framework-free: `Routing\{AdminRouteNames, AdminUrlGeneratorInterface, NullAdminUrlGenerator, CookieDomain}`,
  `Cache\{SimpleCache, SimpleCacheInterface}`, `Cache\Abstract\{AbstractLocalCache, AbstractLocalCacheInterface,
  AbstractLocalCacheWarmerInterface}`.
- docs/20-architecture/packages.md lists what is omnibridge/symfony's.

### Moved: the console to omnibridge/symfony (plan "omnibase agnostic", step F5c, item 3, slice 2, 2026-11-03)

- `Base\Console\{Application, Command, Console}` and the 30 commands of `Base\Console\Command\*` are omnibridge/symfony's
  `Omnibridge\Symfony\Omnibase\Console\*`, as they were; their former names are aliased (former-names.symfony.php,
  FormerNamesTest 262), so `bin/console`'s `use Base\Console\Application;`, an application's command extending
  `Base\Console\Command` and config/services/core.php's service ids keep working. `Base\Console\ConsoleInterface` stays.
- The commands' names, options and output are unchanged (lesringostars' TranslationKeysTest: `translation:crud`,
  `:entities`, `:enums` print the same keys).

### Changed: roles, hot parameters and the field types off Symfony (plan "omnibase agnostic", step F5c, item 3, slice 1, 2026-11-02)

- `SecurityPolicy` reads omnisense/omnibridge's `Contract\Roles` (`#[Autowire(service: Roles::class)]`, in the core's
  `ContractsPass`); a Security `RoleHierarchyInterface` given in its place still works, deprecated until 6.0.
- `HotParameters::warm()`: the hot parameters loaded without a kernel event. `HotParameters` implements
  `HotParameterBagInterface`, which now extends omnibase's own `ParameterBagInterface`, no longer DI's.
- The field types are no longer given the `twig` service (config/services/field-types.php); `FileType` (and `ImageType`'s
  thumbnail) takes `Base\View\AssetPaths`, optional, an alias of `twig` (config/services/twig.php).
- Moved to omnibridge/symfony (`Omnibridge\Symfony\Omnibase\`): `Service\HotParameterBag`, `Service\ParameterBag`,
  `Subscriber\HotParameterBagSubscriber`; to omnibridge/doctrine (`Omnibridge\Doctrine\Omnibase\`):
  `DatabaseSubscriber\DemoAccountLockSubscriber`. Their former names are aliased (former-names.symfony.php,
  former-names.doctrine.php).

### Changed: an account's forms served beyond Symfony (plan "omnibase agnostic", step F5c, second part, 2026-11-02)

- BaseTrait's `getNotifier()`, `getTranslator()`, `getLocalizer()`, `getSettingBag()`, `getEntityHydrator()` and
  `getObfuscator()` (and their `find*()`) ask the registry first (`Runtime::find()`, as `getMediaService()` and
  `getParameterBag()` did): a host that binds them (omnibridge/laravel) answers them, Symfony's locator after.
- The neutral form layer: an empty string given as a field's empty data stays a string (Symfony's `TextType`), also when
  the host sends the field as null; the binder's refusals are translated in the `validators` domain; a class-level
  uniqueness without its class is looked up in the form's validation entity; `FormSubmission::withError()` refuses a
  submission once it holds; `FormProcessor::readers()` registers omnibase's attribute readers.
- `Base\Auth\Registration` (in `Base\Services`): a sign-up once its form holds, approved, its password, named, kept, the
  same on every host; `SecurityController::Register()` uses it (its private `nameAccount()` moved there as `name()`).
- `Localizer` reads its default locale from the host's `Locales` contract where `kernel.default_locale` is unset.

#### Fixed

- The profile's editor (`UserProfileController::Edit()`) answered 500 on a name another account has (the database's
  refusal), and the session was signed out with it: the account written is held to its class's rules first
  (`Base\Form\Definition\RecordRules`), the refusal said under its field, the account read again as it is stored.
- The sign-up's terms of use were checked by the browser alone: `AccountForms::signUp()`'s `agreeTerms` is refused
  unticked (`NotBlank`, groups `Default` and `new`), on top of the form; `user.agree_terms` in the validators' catalogues.
- A rule of the form's data (a model's `#[Assert\Count]`) on a field with `error_bubbling` stood under the field in the
  neutral `FormProcessor`: it is the form's error, as Symfony's mapper gives it (lesringostars' newsletter with nothing ticked).

### Changed: the request, the session and the events on contracts (plan "omnibase agnostic", step F5c, first part, 2026-11-02)

- The services that read Symfony's request stack read omnisense/omnibridge's `Omnibridge\Contract\Requests` (the visitor's
  address and headers, the address, the route, the session's values and flash messages; `Runtime::requests()`, Symfony's
  `StackRequests` in omnibridge/symfony, Laravel's `LaravelRequests` in omnibridge/laravel): `AbstractEventDispatcher` (and the
  event's request), `SpamChecker`, `Referrer`, `BrowserPlusChannel` (the `browser+` flashes), `DemoAccountLockSubscriber`. A
  `RequestStack` given still works until 6.0, adapted by `Base\Http\RequestsOf`. Services keep their arguments' order.
- The entity events go through any PSR-14 dispatcher: `AbstractEventDispatcher` takes `Psr\EventDispatcher\EventDispatcherInterface`
  and dispatches with `Omnibridge\Event\Events::dispatch()` (Symfony's is given the name, any other the event alone);
  `AbstractEvent` is an `Omnibridge\Event\NamedEvent` (`eventName()`, `setEventName()`), so a Laravel listener of `user.register`
  hears it. `UserSubscriber` reads who is signed in through `Omnibridge\Contract\Access` (Symfony's token storage deprecated) and
  gives its listeners to a host that listens by name (`listen()`).
- `Launcher` and `MaintenanceProvider` ask `Omnibridge\Contract\Access` (Security's checker and token storage deprecated).
- The Notifier links through omnibase's router or, on a host without it, the host's `Omnibridge\Contract\Urls` (`urlOf()`,
  `pathOf()`, `currentRoute()`, `domain()` on `BaseNotifier`). A notification's e-mail without Symfony's Twig bridge is
  `EmailComposer`'s (`NotificationMessages::composed()`), a plain symfony/mime e-mail in the Notifier's `EmailMessage`.
- `Localizer` takes omnibase's `Base\Service\ParameterBagInterface` as well as the container's parameters.

### Moved: Symfony host code to omnibridge/symfony (step F5c, first part, 2026-11-02)

- `Base\Bridge\Symfony\FrameworkLocator`, `Base\Subscriber\{TwigSubscriber, TemplatedEmailSubjectSubscriber, AnalyticsSubscriber}`,
  `Base\Cache\Warmer\WebpackCacheWarmer` are `Omnibridge\Symfony\Omnibase\`'s by the F5b rule, their former names in
  `src/Resources/former-names.symfony.php` (225 former names). `FrameworkAccessorsTrait`'s deprecated accessors answer null where
  omnibridge/symfony is not installed. Nothing outside src/Twig names the Twig layer any more (TwigIsOptionalTest's host list is empty).
- Foundation 801 -> 799, agnostic core (`bare --strict`) 704 -> 705.

### Moved: the Symfony form layer to omnibridge/symfony (plan "omnibase agnostic", step F5b, 2026-11-02)

- 78 classes are `Omnibridge\Symfony\Omnibase\`'s, each under its former path: `Base\Field\Type\*` (39), `Base\Form\Type\*`
  (17), `Base\Form\{Extension, Transformer, Traits}\*`, `Base\Form\Common\{AbstractType, FormTypeInterface}`,
  `Base\Form\{FormFactory, FormProxy, FormProcessor}` and their interfaces, and `SymfonyKinds`. Former names kept until 6.0
  (`src/Resources/former-names.symfony.php`, `Base\Database\FormerNames`), resolved where the bridge is installed:
  **a Symfony site moves its omnibridge/symfony lock with the core.**
- `FormerFormTypesPass`: Symfony Form finds a moved type and its extensions under both names. `FormStub` and
  `BaseBundle::symfonyFormDir()`: the `App\Form\` convention reads the bridge's folder too. `FormProxy::createProcessor()`
  compares declared names.
- `Omnibridge\Contract\Validation` is a contract of the runtime under Symfony (`SymfonyValidation`);
  `DefinitionForms` is a service.
- A hand-built form factory's `PreloadedExtension` knows a moved type by its new name.
- Agnostic core 709 -> 702, Foundation 807 -> 799 (decisions of the plan: `--strict` refuses the bridge).

### Added: the neutral form layer, first part (plan "omnibase agnostic", step F5b, 2026-11-02)

Marco's answer 4: every site has forms and the admin builds its own from the Fields, so forms are
no longer Symfony's alone. See [docs/20-architecture/forms.md](docs/20-architecture/forms.md).

- **Kinds**: `Base\Field\Kind` (the neutral kinds: text, textarea, email, choice, association,
  date, file, image, collection, boolean...) and `Base\Field\Kinds` (a host's map, kind => its own
  name; Symfony's `Base\Bridge\Symfony\Form\SymfonyKinds`, read by name). The 45 field descriptors
  name a kind (`setKind()`) in place of a form type class; `getFormType()` answers the same Symfony
  type as before (tests/Field/KindsTest). `setFormType()` stays, the escape hatch: a type a kind maps
  to sets that kind. `FieldDescriptor::getKind()`, `getConstraints()`/`addConstraint()`,
  `getOptions()`/`getOption()`; the same on the fields' fluent surface (`setKind()`, `addConstraint()`,
  `setOptions()`, `setOption()`).
- **`Base\Form\Definition\`**: `FormDefinition` (fields, groups, actions, method, target, token intent,
  data class, host-only options; `fromFields()` for a CRUD page), `FormBinder` (submitted values <=> the
  data, by kind, Symfony's messages for what cannot be read, transformers as callables), `Binding`,
  `FormView` (a plain tree with Symfony's var names; `toArray()` for React), `FormProcessor` (bind, token
  through `Tokens`, validation through `Validation`, kept through `UnitOfWork`; in `Base\Services`),
  `FormSubmission`, `Properties` (reads and writes the data without property-info).
- `Base\Field\Choice\Countries`: a country field's choices, which `CountryType` held (it delegates
  there), so describing a country field loads no form library.
- The agnostic core (`bare --strict`) 699 -> 709, the Foundation 796 -> 807;
  `BareTest::testTheFormLayerWorksWithoutAFormLibrary`, tests/Form/Definition/FormLayerTest.

### Added: the services available everywhere (plan "omnibase agnostic", step F5a, 2026-11-02)

Marco's answer 3: the services stay available everywhere. See
[docs/20-architecture/services.md](docs/20-architecture/services.md).

- **`Base\Services::definitions()`**: the core's services as data (class, id, contracts, arguments by
  name), read by Symfony's container (`config/services-list.php`, loaded last: a listed service the
  configuration does not define is defined from the list, a listed contract it does not alias is
  aliased), by Laravel's (omnibridge/laravel's `Container\Definitions`) and by omnisense/omnibridge's
  plain-PHP `Omnibridge\Container` (`fromDefinitions()`). Today: the obfuscator and its compressions,
  MediaService, Paginator, Translator, Stories, EmailComposer.
- **Views in place of Twig's environment**: the notifier (`BaseNotifier::getViews()`, in
  `BaseNotifierInterface` in place of `getEnvironment()`), `EmailComposer` and `InlineImages` (the picture
  through omnibridge's `ViewSources`; the text part written in PHP, `EmailComposer::text()`),
  `Notification::renderHtml()` and its template check, `NotificationMessages::email()`,
  `MediaService`/`FileService`, `Sitemapper`, `QrSheet`, the sharing adapters, `WysiwygEnhancer`/`EditorEnhancer`,
  `ScreenResponder`. Under Symfony `Omnibridge\Contract\Views` is omnibridge/twig's `TwigViews` over `twig`
  (config/services/twig.php), and a contract of the runtime (ContractsPass). Twig's environment is still
  accepted, deprecated until 6.0 (`Base\View\ViewsAdapter`).
- **On contracts**: `AbstractEventDispatcher` and `BaseNotifier` on omnibridge's `UnitOfWork` (the entity
  manager deprecated, `getEntityManager()` for `getUnitOfWork()`); `Paginator`/`Pagination` accept `Urls`
  (`advanced_router` under Symfony, as before); `Translator` reads omnibridge's `Environment`; `Obfuscator`
  takes its compressions in its constructor too; `DemoAccountLockSubscriber`'s rule is
  `Base\Demo\DemoAccountLock`'s.
- `bare --strict` +3 (`Base\Services`, `Base\View\ViewsAdapter`, `Base\Demo\DemoAccountLock`); BareTest builds
  the services with `Omnibridge\Container` alone (`tests/Services/container-without-framework.php`);
  `tests/ServicesTest` checks the list against Symfony's container; `tests/Notifier/Mail/EmailComposerTest`.
- `bare --install` takes omnisense/omnibridge from its checkout when one is mounted beside the core.

### Changed: Doctrine moves to omnibridge/doctrine (plan "omnibase agnostic", step F3, 2026-11-02)

The core's Doctrine classes are omnibridge/doctrine's, under `Omnibridge\Doctrine\Omnibase\` - 142 classes, by
one rule: `Base\Bridge\Doctrine\X` is `Omnibridge\Doctrine\Omnibase\X`, any other `Base\X` is
`Omnibridge\Doctrine\Omnibase\X`. See [docs/20-architecture/doctrine.md](docs/20-architecture/doctrine.md).

- Moved: `Base\Bridge\Doctrine\*` and `Behaviour\*` (the unit of work, backend, shapes, repositories, query
  pages, the behaviours' Doctrine backends, the mapping, types, configuration, repository factory, registry,
  snapshot); `Base\Database\Mapping\{ClassMetadataManipulator, ClassMetadataCompletor, ClassMetadataFactory,
  NamingStrategy}`; the DBAL types (`ArrayType`, `DateTimeTypeUTC`, `MySQLSetType`, the UTC and passkey types);
  `Base\Database\{Walker, Function, Filter, Middleware, Event}\*`, `Schema\Describer`; the finder's Doctrine
  compiler `ServiceEntityParser` and `ServiceEntityRepository`; **the 81 repositories** `Base\Repository\**\*Repository`;
  the Doctrine listeners `Base\DatabaseSubscriber\{Attribute, Intl, InverseCollectionCache, TrackingPolicy}Subscriber`
  and `Base\EntitySubscriber\{Extension, Trasheable, Versionable, Setting}Subscriber`.
- Stays: the neutral half - the finder language's parser and AST, `Entities`, `Repositories`, the repository
  interfaces and `Aliases`, the shapes, the schema rules, `EnumType`, `SetType`, `Enumerations`, `Utc`, the
  behaviours' attributes and their backends' contracts, the listeners' logic (`Base\Database\Lifecycle\*`),
  `DemoAccountLockSubscriber` (F5a), `ThreadSubscriber`, `UserSubscriber` and `ConnectionSubscriber` (F5c).
- **The former names keep working** until 6.0: `src/Resources/former-names.php`, required by `Functions.php`,
  aliases each one (the list: `src/Resources/former-names.doctrine.php`, read by the new
  `Base\Database\FormerNames`), whichever name is loaded first - PHP checks an `instanceof` or a type written
  with a name without autoloading it, so the moved class loaded by its new name declares its former one at once.
  `BaseExtension` sets `.container.dumper.inline_class_loader` to false: Symfony's debug container included the
  services' files itself, past the autoloader. No deprecation is raised yet: the core's entities still name their
  repositories by their former names, so Doctrine's metadata and `MetadataSnapshot` are those of before.
- The moved services are registered under their new names, their former ids aliased; BaseBundle maps the
  moved repositories `Base\Repository\X` => `App\Repository\X` from omnibridge/doctrine's folder and registers
  them under their former ids (`AbstractBaseBundle::setMapping()`'s `$declaredNamespace`); a former name's
  App\ alias is recognised by the class it stands for (`declareAlias()`, `setAlias()`).
- `Base\Bridge\Symfony\FrameworkLocator`'s Doctrine half is `Omnibridge\Doctrine\Omnibase\DoctrineLocator` (its
  methods call it; the core's validators, `EditorController`, `BaseService` and `FrameworkAccessorsTrait` call it
  directly). `#[Cache]`'s regions, `#[Vault]`'s unique constraints and `#[OrderColumn]`'s order field - their
  `loadClassMetadata()`, typed on Doctrine's `ClassMetadata` - are `Behaviour\DoctrineMappingHooks`', run by
  `AttributeSubscriber` and the manipulator before an attribute's own (deprecated) Doctrine-typed hook.
- An entity's `repositoryClass` may name a repository interface of the core: omnibridge/doctrine's
  `RepositoryFactory::implementation()` builds the repository `Base\Repository\Aliases::INTERFACES` binds to it,
  and under Symfony `RepositoryInterfacesPass` adds the interfaces to DoctrineBundle's repository locator.
- `UnitOfWorks::ADAPTERS`, `Pagination::ADAPTERS` and `Aliases::INTERFACES` name the moved classes;
  `MetadataSnapshot` names a moved filter or type by its former name (it compares the mapping, not where a
  class lives). `RepositoryStub` writes the App\Repository\ stubs from omnibridge/doctrine's folder.
- `bare --install` installs omnibridge/doctrine from its checkout when one is mounted beside the core
  (the harness's compose.checkouts.yml).
- The Foundation 903 -> 793 (-127 moved, +17 listed nowhere though they loaded without the framework); the
  agnostic core (`bare --strict`) 691 -> 696 (-12 moved, +17).
- The core still requires omnibridge/doctrine and doctrine/* - the composer.json switch is step F6 - so that
  the sites keep everything; nothing changes for them.

### Fixed: a thread's followers and mentions are computed (2026-11-02)

`Base\EntitySubscriber\ThreadSubscriber`'s persistence hooks were registered nowhere: no reply was followed
by its parent's owners, no `Mention` was stored, so `thread.mentioned` was never mailed. Its logic is now
`Base\Database\Lifecycle\ThreadParticipants`, on omnibridge's neutral `PrePersist` and `OnFlush`;
ThreadSubscriber relays Doctrine's `prePersist` and `onFlush` to it (`doctrine.event_listener`, the
enhancer in a `service_closure`).

- A reply gets its parent's owners as followers at its creation, and at the flush that moves it under
  another parent (its collection recomputed; the former `preUpdate` hook, where Doctrine writes no
  collection change, is gone).
- Each user a thread's text mentions (its `<mention>` tags, every translation) has one `Mention`, added or
  removed at the flush that writes the text or makes the thread publishable: the new ones persisted and
  their change sets computed in the running flush, the old ones removed.
- `thread.publishable` mails the authors (`getAuthors()` when the class has one, its owners otherwise: Thread
  has none, the publication threw on the entities without an alias) and each mentionee who is not an author.
- `Base\Bridge\Doctrine\DoctrineUnitOfWork::recompute()` computes the first change set of an entity persisted
  during the flush (it threw on it).
- **For the sites**: a thread whose content already mentions members mails them once, at its publication.

### Changed: BaseTrait names contracts only (2026-11-02)

Plan "omnibase agnostic", step F2f. See docs/20-architecture/contracts.md (BaseTrait on contracts only).

- Each accessor of a contract comes in two forms: `getX()` answers the service or throws
  `Omnibridge\Exception\MissingContractException` (it answered null), `findX()` answers it or null:
  `AttributeReader`, `SettingBag`, `EntityHydratorInterface` (was `EntityHydrator`), `MediaService`, `Obfuscator`,
  `IconProvider`, `LocalizerInterface`, `BaseNotifierInterface`, `TranslatorInterface`, `TradingInterface`,
  `ParameterBagInterface`. `isEntity()` answers a bool, and throws without persistence.
- **Deprecated**: the framework's accessors (`getRouter()`, `getTwig()`, `getDoctrine()`, `getObjectManager()`,
  `getEntityManager()`, `getRepository()`, `getClassMetadataManipulator()`, `getTokenStorage()`,
  `getRequestStack()`, `getFirewallMap()`, `getSlugger()`, `getService()`), moved to
  `Base\Traits\FrameworkAccessorsTrait` (still used by BaseTrait, same types and nulls, an `E_USER_DEPRECATED`
  per call naming the replacement); removed at 6.0. The core's host code reads them from
  `Base\Bridge\Symfony\FrameworkLocator`.
- The core's callers: `Widget\Attachment` and `Widget\Page` link through `Link::to()`; Uploader, Wysiwyg and
  ConstraintValidator use the `find` forms; BaseService, the validators, EditorController and Notification's Twig
  go through FrameworkLocator; UniqueEntityValidator asks the manager of the entity's class (it passed the class
  as `$reopen`). The omnibase/* bundles' entities link through `Link::to()` too.
- Tests: `tests/Traits/BaseTraitContractsTest` (BaseTrait names no framework class, each type a contract, the
  get/find pairs, the deprecated accessors called nowhere in src/), `tests/Traits/BaseTraitMissingContractTest`,
  BareTest's seams without the framework.

### Fixed: `DoctrineRepositories` autowired by Laravel again (2026-11-02)

- Step F2d had typed its constructor `DoctrineUnitOfWork|EntityManagerInterface`; Laravel's container resolves
  no union type, so omnibridge/laravel could not make `Repositories` (lesringostars-laravel's
  RepositoryInterfacesTest). It takes the entity manager alone again; `DoctrineBehaviours::register()` builds it
  from a unit of work's entity manager for `Backends`. `UnitOfWorksTest::testTheRepositoriesAreAutowiredByAnyContainer`.

### Changed: the trash and vault filters as scopes, the paginator on Pages (2026-11-01)

Plan "omnibase agnostic", step F2e. See docs/20-architecture/doctrine.md (The scopes and the pages).

- `Base\Database\Scope\TrashScope` ("trash": a `#[Trasheable]` entity's deletion field => null) and
  `VaultScope` ("vault": a `#[Vault]` entity's vault field => null or the environment) answer
  omnisense/omnibridge's `Scope`. Doctrine's SQL filters compile them, names and SQL unchanged
  (`TrashFilter`, `VaultFilter`: the metadata snapshot's filter constraints are identical).
- `Base\Bridge\Doctrine\DoctrineBackend` answers omnibridge's `Backend`: the scopes enabled and disabled by
  name (Doctrine's `trash_filter` and `vault_filter`), what it supports, its unit of work; the Symfony
  service `Omnibridge\Persistence\Backend`, and in `Backends` for `DoctrineUnitOfWork`.
- `TrashManager` on the unit of work: `withTrash()` disables the trash scope through the Backend, the
  entity read through its repository, the deletion field cleared by reflection, the extension rows of a
  destroyed entity deleted through `Base\Database\Backend\BulkDeletes` (`DoctrineBulkDeletes`: a DQL DELETE),
  the second-level cache through `EntityCache`. Its constructor accepts the entity manager, deprecated.
- `Base\Service\Model\Pages` (count, slice): what `Pagination` pages besides an array. A Doctrine `Query` is
  adapted to `Base\Bridge\Doctrine\QueryPages` (by name, `Pagination::ADAPTERS`), which does what Pagination
  did with it (the GroupBy and Translatable walkers, Doctrine's Paginator, the count by CountWalker).
  `Paginator::paginate()` takes `array|Pages|Query`. `Pagination::get()` gives the Pages for a query,
  `isQuery()` says whether the rows are Pages, `getQuery()` the page's query when the Pages have one;
  `getCountQuery()` is deprecated (`QueryPages::countQuery()`).
- Tests: `tests/Database/ScopesTest` (the scopes' criteria, the filters' SQL from them, the Backend and
  `withTrash()`, a Pagination over any Pages, a Doctrine query paged as before). `UnitOfWorkPathTest` covers
  `TrashManager`.
- Agnostic core 686 -> 690, Foundation 898 -> 902.

### Changed: the core's services on the unit of work (2026-11-01)

Plan "omnibase agnostic", step F2d. See docs/20-architecture/doctrine.md (The core's services on the unit of work).

- **The services take omnisense/omnibridge's `UnitOfWork`** where they took Doctrine's entity manager, and
  ask it nothing of the ORM: omniauth's stores (`Base\Auth\UserStore` and its subclasses), the sign-in
  listeners (`TrackConnections`, `UpdateAccount`, `WelcomeBack`), `ConnectionTracker`, `UserTracker`,
  `UserChecker`, `InAppChannel`, `Invitations`, `WebPushService`, `Signatures`, `VersionManager`,
  `CollabRoomResolver`, `SettingBag`, `GroupManager`, `HeadingEnhancer`; the controllers
  `SecurityController`, `LocalizerController`, `Api\ThreadController`, `Client\NotificationController`,
  `UX\AutocompleteController`, `UX\AutovalidateController` (typed `UnitOfWork`); the commands
  `user:create`, `user:notifications`, `autocomplete:entities`, `icon:entities`, `uploader:images:crop`,
  `thread:publishable`. Their protected `$entityManager` is `$unitOfWork`.
- **The entity manager is still accepted**, deprecated, as the other half of a union
  (`UnitOfWork|EntityManagerInterface`, until 6.0): `Base\Database\UnitOfWorks::of()` adapts it
  (`UnitOfWorks::ADAPTERS`; `register()` for another persistence). omnibridge/laravel, the bundles and the
  tests construct them unchanged. `Base\Console\Command` keeps its `$entityManager` (deprecated) beside a
  new `$unitOfWork`.
- `UnitOfWorks::repositories()` and `UnitOfWorks::shapes()`: the repositories (`Repositories`, now
  registered in `Backends` for `DoctrineUnitOfWork`) and the entities' shapes of a unit of work.
- What only a persistence does: `Base\Database\Backend\{RowLocks, ConditionalUpdates, EntityCache}`, in
  `Backends`; Doctrine's `Base\Bridge\Doctrine\Behaviour\{DoctrineRowLocks, DoctrineConditionalUpdates,
  DoctrineEntityCache}` (to omnibridge/doctrine at F3). A `DoctrineUnitOfWork` registers Doctrine's
  backends when it is made (`DoctrineBehaviours::ensureRegistered()`).
- Symfony: the service `Omnibridge\Persistence\UnitOfWork` (`DoctrineUnitOfWork` over the entity manager),
  the union `Doctrine\ORM\EntityManagerInterface|Omnibridge\Persistence\UnitOfWork` aliased to it; the
  converted services' definitions pass it.
- `Base\Repository\User\Identities` (IdentityRepository's finders, aliased); `Passkeys::findOneByCredential()`.
  `UserPasskeys::of()` reads `findBy(['user' => ...], ['id' => 'ASC'])` where it built the same query.
- `CollabRoomResolver::resolveEntity()` asks the shapes whether the object is an entity (Doctrine's
  `isTransient()` before).
- Tests: `tests/Database/UnitOfWorksTest` (the adaptation, the container's unit of work, the repositories
  and shapes through it, a row locked and a field compared and set on SQLite), `tests/Database/UnitOfWorkPathTest`
  (the converted files ask the entity manager nothing, name it only in the union).
- Agnostic core 681 -> 686, Foundation 893 -> 898 (`UnitOfWorks`, the three backends, `Identities`).

### Fixed: `#[Cache(associations: "MANY_TO_MANY")]` caches the many-to-many associations (2026-11-01)

- `Cache::MANY_TO_MANY` was the string `"ONE_TO_ONE"`: `associations: "MANY_TO_MANY"` cached no association,
  `associations: Cache::MANY_TO_MANY` the one-to-one ones. It is `"MANY_TO_MANY"`. No entity of the core, its
  bundles, lesringostars or its twin uses it (they all say `"ALL"`), so no cache region changes and the metadata
  snapshot is identical. `tests/Database/Attribute/CacheAssociationsTest`.

### Fixed: a dead copy of ExtensionSubscriber removed (2026-11-01)

- `src/EntitySubscriber/HookExtensionSubscriber.php` declared a second `Base\EntitySubscriber\ExtensionSubscriber`,
  an older copy (it named `AbstractMetadata`, which no longer exists) that nothing loaded nor referenced: removed,
  with its phpstan baseline entries. `tests/SourceFilesTest` checks each file of src/ declares the class its path
  names.

### Added: the entity's shape (2026-11-01)

Plan "omnibase agnostic", step F2c. See docs/20-architecture/doctrine.md.

- `Base\Database\Mapping\EntityShape`: what the core reads of an entity's mapping - class, root,
  identifier, fields and their types, associations (kind, side, target, mappedBy/inversedBy),
  discriminator - one `Omnibridge\Schema\Entity` of the Description. `EntityShapes` gives them:
  `DescriptionShapes` (from a Description, no ORM), `Base\Bridge\Doctrine\DoctrineShapes` (each
  class's metadata through omnibridge/doctrine's `MetadataDescription`, registered for
  `DoctrineUnitOfWork` in `Backends`). `AbstractAttribute::shape($event, $class)`.
- `#[Alias]`, `#[Associate]` and `#[OrderColumn]` answer the neutral hooks and read the shape; their
  Doctrine-typed hooks are gone. `Associate::preLifecycleEvent()`/`postLifecycleEvent()` are
  `toIdentifier()`/`toEntity()`. OrderColumn's lazy ordering of a PersistentCollection is
  `OrderColumnBackend` (`DoctrineOrderColumnBackend`), the rest sorts at once; it reads a parent's
  private property where it is declared.
- `ClassMetadataManipulator::getShapes()`, `getShape()`; `getAssociationType()` (so `isToManySide()`
  and the other kinds), `isOwningSide()` and `isInverseSide()` read the shape for an entity or a class,
  the metadata as it stands for a `ClassMetadata` (the mapping hooks give one while it is built).
  `EntityHydrator`'s defaults, `fromProxy()` and `getOriginalEntityData()` read the shape.
- Agnostic core 677 -> 681, Foundation 889 -> 893 (on step F4's counts).

### Changed: Twig is optional, over neutral view helpers (2026-11-01)

Plan "omnibase agnostic", step F4. Marco: the core's Twig extensions are there when Twig is used
and take nothing away when it is not; the view engine is the application's choice.

- **twig/twig, twig/cache-extra, cssinliner-extra, inky-extra, intl-extra and omnibridge/twig move
  from `require` to `suggest`.** A Twig application requires them (omnibridge/symfony and
  omnibridge/laravel require twig/twig and omnibridge/twig; the extras are the application's:
  the core's e-mail frame uses inky and the CSS inliner, its breakpoints and widgets `{% cache %}`).
- **Neutral view helpers, `Base\View\*`**, no Twig type: `Formats`, `Captions`, `Dates`, `Money`,
  `Pagination`, `Links`, `Enums`, `Menus`, `Bell`, `Pictures`, `Commons`, `Wysiwyg`, `Demo`,
  `DeployVersion`, `Widgets`, and the `AssetPaths` contract. The logic of the Twig functions and
  filters moved there; each extension of `src/Twig` is a thin wrapper over them, under the same
  names and constructors, so no template and no host changes (omnibridge/laravel builds them as
  before). `Dates` and `Money` format with PHP's intl as twig/intl-extra did (ViewHelpersTest
  compares them), so FunctionTwigExtension and TradingTwigExtension no longer need intl-extra.
  `config/services/view.php` registers `Commons` and `Wysiwyg`, which the screens' Pictures and
  Texts contracts (`Base\Bridge\Symfony\MediaPictures`, `WysiwygTexts`) now stand on, without Twig.
- **The Twig layer is registered only where Twig is installed**: `config/services/twig.php` returns
  first without it, and holds every service of the layer (DemoTwigExtension, LocalBusinessTwigExtension,
  CommonsTwigExtension, AccountScreens and the three host classes below came from demo.php,
  local-business.php, commons.php, core.php and form.php); services-decoration.php (the environment,
  its loader, its app variable), services-public.php (the `twig.*` aliases) and services-fix.php
  (Symfony's AssetExtension) register their Twig part under the same condition; the tag renderers'
  autoconfiguration left BaseExtension for it; BaseExtension merges the form themes only where
  TwigBundle set `twig.form.resources`. twig.php also lost its copies of database.php's and
  form.php's services (NamingStrategy, EntityHydrator, ClassMetadataManipulator, AttributeReader,
  the voters, the validators...): identical, but loaded last they won over the files that own them.
- **Nothing outside `src/Twig` names `Base\Twig\`** but the layer's three Symfony host classes
  (`Base\Subscriber\TwigSubscriber`, `Base\Cache\Warmer\WebpackCacheWarmer`,
  `Base\Form\Extension\FormTypeWebpackExtension`, for omnibridge/symfony at F5c):
  `AccountScreens` asks Twig's functions and filters by their names (|thumbnail,
  oauth_available(), push_available()...) instead of the extensions' classes; the form types,
  `RescueController` and `WysiwygEnhancer` type Twig's `Environment`, `ImageType` asks
  `Base\View\AssetPaths` for its thumbnail's path; `TagRendererPass` names the environment by its
  service id; `Base\Service\BaseService` no longer declares Twig's `RuntimeExtensionInterface`
  (no Twig function was answered by it). Guard: `tests/View/TwigIsOptionalTest.php`.
- **bare**: on `var/bare/vendor`, which has no Twig any more, a class of `src/Twig` that cannot
  load for want of Twig's is reported `"optional": "twig/twig"`, not as a failure; every other class
  is measured as before (`BareTest::testTwigIsOptional`), and the helpers run there under `--strict`
  (`testTheViewHelpersWorkWithoutTwig`, tests/View/helpers-without-twig.php). Foundation 873 -> 889,
  agnostic core 660 -> 677.
- `MediaService` and `FileService` take Twig's environment as optional (`?Environment`): the file
  and image rules need none, only the media tags (audio(), video(), imagify(), lightbox()), which
  refuse aloud without it; tests/Service/media-without-framework.php builds MediaService with none.
- Still Twig-bound (F5a, services on contracts): the services that render a template of the core
  through Twig's environment (the media tags above, the notifier and its mails, Sitemapper,
  QrSheet, the sharing adapters, ScreenResponder) take it in their constructors; they load without
  Twig, and are to render through `Omnibridge\Contract\Views`.

### Added: the behaviours' persistence parts, the listeners' logic apart (2026-11-01)

Plan "omnibase agnostic", step F2b (second part). See docs/20-architecture/doctrine.md.

- `#[Vault]`, `#[Slugify]`, `#[Cache]` and `#[Uploader]` answer the neutral hooks; their Doctrine-typed
  hooks are gone (none was overridden in the bundles nor the sites). What only a persistence can do is
  asked of `Base\Database\Attribute\Backend\{VaultBackend, SlugifyBackend, CacheBackend}`, registered
  per unit of work class in `Backends` (`UnsupportedBehaviourException` when the persistence has none);
  Doctrine's are `Base\Bridge\Doctrine\Behaviour\*`, registered by `AttributeSubscriber`.
- `Vault::preLifecycleEvent()` and `postLifecycleEvent()` are `sealFields($entity)` and
  `revealFields($entity)`; `Cache::payload()` is `CacheBackend::evict()`; Slugify's schema check and its
  memo are `DoctrineSlugifyBackend`'s. `#[Cache]` numbers the association types itself
  (`FLAG_ONE_TO_ONE`...: Doctrine's values), so the attribute is made without Doctrine.
- The PreUpdate relay passes on only the fields a neutral hook gave a new value: it compared every
  field with the change set of when the event was made, so a hook's `recompute()` (Uploader) or a
  Doctrine-typed hook's change would have been put back.
- `Base\Database\Lifecycle\{SettingsInvalidation, TrashRecorder, RevisionRecorder}`: the logic of
  SettingSubscriber, TrasheableSubscriber and VersionableSubscriber, which keep their names, their
  wiring and their Doctrine part (`TrasheableSubscriber::expiry()` is `TrashRecorder::expiry()`).
- Agnostic core 652 -> 660, Foundation 865 -> 873.

### Added: the behaviours' neutral hooks (2026-11-01)

Plan "omnibase agnostic", step F2b (first part).

- `AbstractAttribute` has ten neutral hooks - `onPrePersist()`, `onPreUpdate()`, `onPreRemove()`,
  `onPostLoad()`, `onPostPersist()`, `onPostUpdate()`, `onPostRemove()` with the entity's event,
  `onPreFlush()`, `onOnFlush()`, `onPostFlush()` with the flush's event and the entity - taking
  omnisense/omnibridge's `Omnibridge\Persistence\Lifecycle\*` events (the entity, its unit of work,
  `recompute()`), no ORM type.
- `AttributeSubscriber` relays Doctrine's events to them: the neutral hook first, then the
  Doctrine-typed one, which the attributes of the applications and bundles may still override
  (deprecated, removed at 6.0); a new value a neutral `PreUpdate` gives a changed field goes to
  Doctrine's update.
- `Base\Bridge\Doctrine\DoctrineUnitOfWork`: omnibridge's `UnitOfWork` over the entity manager, the
  first of the persistence contracts' Doctrine adapters.
- Timestamp, Timezone, Blameable, Countify, Hashify, Randomize and GenerateUuid answer the neutral
  hooks; Alias, Associate, Cache, OrderColumn, Slugify, Uploader and Vault reach Doctrine's metadata
  and unit of work inside their hooks and follow with step F2c (the entity's shape).

Test: `tests/Attributes/NeutralHooksTest` (the hooks' events, the moved behaviours, the unit of work
over Doctrine).

### Changed: the enumerations are plain PHP classes (2026-11-01)

Plan "omnibase agnostic", step F2a.

- `Base\Database\Type\EnumType` and `SetType` no longer extend Doctrine's `Type`: same names, same
  static API (`getPermittedValues()`, icons, colours, `getText()`, `getStaticName()`...), and their
  conversions as `toDatabaseValue()` and `toPHPValue()`. The 20 enumerations of the core and those of
  the applications and bundles change nothing (none overrode a DBAL method).
- On Doctrine an enumeration is a `Base\Bridge\Doctrine\EnumTypeAdapter` of it, registered under its
  name by `Types::register()`, and under Symfony by `Base\Bridge\Doctrine\EnumerationsMiddleware` when
  the connection is made: `EnumerationTypesPass` takes the enumerations out of DoctrineBundle's
  `doctrine.dbal.types`, where `BaseExtension` and the bundles still list them. `EnumType::declaration()`
  is `EnumTypeAdapter::declaration()`.
- `Base\Database\Type\Enumerations` knows them by name: `DemoAccountRegistry`, `EnumNouns` and
  `enum()` (Twig) ask it in place of DBAL's registry; `getDoctrineType()` answers an enumeration's
  column with an instance of the enumeration, as before.
- The translation key of a value is unchanged (`thread_state.publish`): `EnumNouns` leaves the
  abstract classes out of the chain whatever they are. `Translator::parseClass(PARSE_EXTENDS)` ends
  at `enum_type`, no longer `enum_type.type`.
- The Foundation 864 -> 865, the agnostic core 629 -> 652 (the enumerations load without Doctrine).

Tests: `EnumTypeTest` (the conversions through the adapter, the enumeration no Doctrine type),
`SchemaManagerTest` (the columns through the adapters), `BareTest` (an enumeration under
`--strict`), `HandBuiltEntityManagerTest` (Symfony's metadata and types as recorded before).

### Fixed: the finder language's words do what they say (2026-11-01)

Before step F2 of the plan "omnibase agnostic": the defects seen while the parser left Doctrine (F1).

- `LowerEqualTo`, `GreaterEqualTo`, `OlderEqualTo` and `YoungerEqualTo` compare with `<=` and `>=`:
  they called `Expr::le()` and `Expr::ge()`, which Doctrine does not have (an Error).
- A negative word is read as itself: `NotStartingWith`, `NotEndingWith`, `NotInstanceOf`,
  `NotClassOf` and `NotMemberOf` were read as their positive word, which they end with
  (`findBySlugNotStartingWith()` compared a field `slugNot`). `NotClassOf` builds `ClassOf`'s
  opposite (it built the same query), `NotMemberOf` is negated.
- `lengthOf<Collection>By` selects the collection's number of members (`SIZE()`): it built
  `LENGTH()` of a joined alias, an invalid DQL.
- A `DateTimeImmutable` compared with a field of a mutable type (datetime, date, time) is bound as the
  `DateTime` of the same moment: the type refused it (`findNextOneBy(['publishedAt' => $immutable])`).
- `Null` and `NotNull` bind no parameter: `findBy...AndParentNull` with an order raised "Too many
  parameters" (lesringostars).
- `ImageCropRepository::oneClosestToRatio()` and `oneClosestToRatioAndSize()` give the ratio as the
  selected expression: they gave it as `__findOneBy()`'s offset (a TypeError).

`finder-parity.json` recorded again for the seven calls these fixes change, and only them:
`findByIdLowerEqualTo`, `findByPublishedAtYoungerEqualTo` (a query where an Error was),
`findByParentNull`, `findByParentNotNull` (no parameter), `findBySlugNotStartingWith` (the parse and
the query), `cacheNextOneByClassOfAndState` (a result where the type refused), `lengthOfTagsByState`
(a result where the DQL was invalid). Test: `tests/Database/Repository/FinderDefectsTest`.

### Added: the finder language read without Doctrine (2026-11-01)

Plan "omnibase agnostic", step F1 (the finder language is Marco's invention, kept whole in the core).

- **`Base\Database\Repository\Finder\Parser`** reads a finder's name and arguments -
  `findOneByEmailAndUsername()`, `cacheOneByStateAndSlugStartingWith()`,
  `countByStateAndPublishedAtYoungerThan()`, `cachePreviousOneByClassOfAndState()`,
  `lengthOfTagsByState()`, every word and special of the language - into a **`Finder\Query`**, its
  neutral form: the internal request and its arguments, the criteria and their options, the
  separator, the column, cache, Eagerly, the request and the special; `criterions()` gives them as
  `Finder\Criterion` objects, `extras()` the specials' numbers and keys. What it asks of the entity
  is a `Finder\Subject` (class, fields, a field or association, a Query-by-Example's criteria); a
  host's request for `...WithRoute` goes through the resolver it is given.
- **`ServiceEntityParser` is the Doctrine compiler of that Query**: its entity's Subject, `read()`
  parses, `execute()` compiles and runs, `parse()` does both as before. Its constants are the
  Parser's, same names. `$eagerly` takes the associations' list an `...Eagerly...` finder always gave
  it, which its `bool` type refused. It moves to omnibridge/doctrine at step F3; omnibridge/eloquent
  compiles the same Query at F7.
- **The parse is the one of before, word for word**: `tests/Database/Repository/FinderParityTest`
  compares, for 57 finder calls (the core's own shapes and one of each word), the parse and the
  query (DQL, parameters, first and maximum results, or what a running finder gave or threw) with
  `finder-parity.json`, recorded on the code of before (`FINDER_PARITY_RECORD=1`).
- Seen while recording, kept as they were for the parity (docs/20-architecture/entities-and-repositories.md):
  `LowerEqualTo`/`GreaterEqualTo` call `Expr::le()`/`ge()`, which Doctrine lacks;
  `lengthOf<Association>By` builds an invalid DQL; a `DateTimeImmutable` bound on a mutable
  `datetime` field is refused; `ImageCropRepository`'s two finders pass their selected expressions as
  `__findOneBy()`'s offset; `NotStartingWith`, `NotEndingWith`, `NotInstanceOf`, `NotClassOf` and
  `NotMemberOf` are misread as their positive word.
- Foundation 860 -> 864, agnostic core 625 -> 629.
- **picqer/php-barcode-generator** leaves the requirements: nothing in the core used it.

Tests: `FinderParityTest`, `tests/Database/Repository/Finder/ParserTest` (each word on an entity known
by its fields alone), `BareTest::testTheFinderLanguageIsReadWithoutDoctrine` (parsed under
`bare --strict`, nothing of Doctrine loaded).

### Added: `bare --strict`, the guard of the agnostic core (2026-11-01)

Plan "omnibase agnostic", step F0: the core is to impose neither Doctrine, nor Twig, nor Symfony
beyond the standalone components kept.

- `docker/bin/bare --strict` loads classes as `bare` does, on the vendor without the framework,
  against a wider set: the framework, every Doctrine package but doctrine/collections (and
  doctrine/deprecations), Twig, omnibridge/doctrine, twig, symfony and laravel, Laravel
  (`Illuminate\`), and every Symfony component outside string, uid, intl, translation, mime,
  mailer, notifier (not its transports), property-access, options-resolver, yaml, password-hasher,
  filesystem, finder, serializer, the contracts and the polyfills. `--strict` also applies to
  `--all`, `--each` and `--names`.
- Without classes it loads `tests/Agnostic.php`: the 625 classes of the Foundation's 860 that pass,
  none of `src/Bridge/`; the list only grows (a class leaves it by a decision written in the plan and
  here, its move to a bridge being one).

Tests: `BareTest::testTheAgnosticCoreLoadsWithoutDoctrineNorTwig`,
`testTheAgnosticCoreIsSortedAndWithinTheFoundation`, `testTheStrictCheckNamesDoctrineAndTwig`. Docs:
`docs/30-operations/harness.md` (The agnostic core: `bare --strict`).

### Changed: glitchr/backup-manager is a suggestion (2026-11-01)

- **glitchr/backup-manager** moves from `require` to `suggest`: it serves the TimeMachine alone
  (`Base\Service\TimeMachine` extends its Manager, and the commands `timemachine:snapshot`,
  `:snapshot:backup`, `:snapshot:restore` and `timemachine:storage`). They are registered only where
  `Backup\Manager\Manager` exists (`config/services/core.php`, the aliases `time_machine` and
  `TimeMachineInterface` in `services-public.php`, the abstract parent in `services-fix.php`); without
  the package, nothing of them is in the container and `base.time_machine` is read by nothing.
- `DEFAULT_EXCLUDES` is `Base\Service\TimeMachineInterface`'s: `BaseConfiguration` reads it there, so
  building the configuration no longer loads the TimeMachine (a fatal error without its parent class).
  `TimeMachine::DEFAULT_EXCLUDES` still answers, inherited.
- **A site that backs up through the TimeMachine requires `glitchr/backup-manager:4.0.*-dev` at its
  root** (docs/10-getting-started.md, docs/30-operations/backups.md). The core keeps it in
  `require-dev` and in `extra.omnibase.bare` (var/bare installs it beside the core, the Foundation
  keeps `Base\Service\TimeMachine`); the harness's application requires it.
- omnibridge/doctrine and omnibridge/twig stay required: the core's column types, naming strategy and
  mapping driver extend omnibridge/doctrine's, its Twig helpers call omnibridge/twig's
  `HelperExtension` (docs/20-architecture/packages.md lists the classes).

Test: tests/Service/TimeMachineOptionalTest.php (the service files and the configuration loaded in a
process whose autoloader finds no `Backup\` class).

### Fixed: a DateField reads as a day (2026-10-31)

`DateField` renders through `crud/field/date` (omnibase/admin), 05/12/2026, where the text template
showed the resolver's default `Y-m-d H:i` (2026-12-05 00:00).

### Fixed: a signed-in member's language before the remembered one (2026-10-31)

- `LocalizerSubscriber` reads a signed-in member's own language before the `LANG` cookie (the
  impersonator's choice first while impersonating): a member whose language is French, arriving on
  the sign-in page with an English browser, had `LANG` set to English by that first page and was
  kept in English after signing in, back office included. The order is `LocalizerSubscriber::choose()`.
- Intl's default locale (`\Locale::setDefault()`) follows the page's language: Symfony's
  `LocaleSwitcher` set it from the request's locale before the subscriber decided it (and in a
  worker it was the previous request's), so amounts formatted without a locale came out in another
  language than the page (`€7,704.99` on a French page).

Test: tests/Subscriber/PageLanguageOrderTest.php. docs/40-commons/pages-and-texts.md (A page's language).

### Fixed: `str_shorten()` counts characters (2026-10-30)

- `str_shorten()` (src/Resources/Functions.php) measures the text in characters (`mb_strlen`), as it
  cuts it (`mb_substr`), and finds a sentence's full stop in characters too (`mb_strrpos`,
  `mb_strpos`, for `SHORTEN_BACK_SHRINK` and `SHORTEN_BACK_EXTEND`): a multibyte text was cut short,
  and at the front or in the middle the cut started past its end (41 `é` to 10 at the front gave
  `[..]` alone). The fix omnibridge/twig's `|str_shorten` had (a38d3ca).

Test: tests/FunctionsTest.php (`testStrShortenCountsCharactersNotBytes`).

### Fixed: the low defects the manual found (2026-10-29)

- `config:dump-reference base` and `debug:config base` dump the tree: `BaseExtension::getConfiguration()`
  returns `BaseConfiguration` (Symfony looked for a class named `Configuration`).
- `doctrine:database:import` reads a `csv` or `txt` file as one sheet named after the file; its
  case fell through into the one that threw "File extension couldn't be determine..".
- `user.newcomer` is dispatched when an update makes an account a newcomer again, as the other
  `user.*` events are; it was dispatched by nothing.
- Twig's `round()` keeps its decimals (`round(3.14159, 2)` is 3.14; it was declared `: int`).
- `|color_name` names a colour, the nearest CSS named colour: `Colors` read a `colors` resource
  bundle symfony/intl does not ship, a `MissingResourceException` on every call. It now holds
  CSS's names (the same in every locale).
- `EditorType` and `WysiwygType` default `empty_data` to `null` (written `'empty_data', null,`
  they declared options `0` and `1`).
- `autocomplete:entities`, `icon:enums`, `timemachine:storage`, `translation:crud`,
  `translation:entities` and `translation:enums` are registered; `icon:enums` and
  `translation:enums` skip the native PHP enums and the enum groups they failed on.
- The harness's `base.yaml` comment says what `use_hot_bag` and `use_setting_bag` do.

Tests: tests/DependencyInjection/ConfigurationReferenceTest.php, tests/Console/RegisteredCommandsTest.php,
tests/Console/DoctrineDatabaseImportCsvTest.php, tests/EntityDispatcher/UserNewcomerEventTest.php,
tests/Twig/MathAndColourFunctionsTest.php, tests/Field/EmptyDataTest.php.

### Deprecated: the options read by nothing

`database.excluded_fields`, `share`, `access_restriction.redirect_on_deny`,
`site.maintenance.exception`, `uploader.warmup`, `files.storage`, `images.autorotate`,
`images.disable_profiler`, `images.warmup`, `images.formats` and
`extension.logging_default_expiry` were declared and read by no code. They are deprecated, not
removed (the configuration tree is public API, and every site's `base.yaml` sets most of them):
a site that sets one gets Symfony's deprecation notice; they go at the next major.

`router.ip_access` is deprecated too (2026-10-30): `RouterSubscriber` reads it only once
`isGranted('VALIDATE_IP', $route)` holds, which no voter of the core decides, so it had no effect.
Wiring it would have made its default, `false`, redirect every request reaching a site by its IP
address, health checks included. The sites no longer set it.

### Removed: two broken commands

`icon:controllers` (it read a router `Base\Console\Command` has not) and `icon:crud` (it asked a
class name whether it was a CRUD controller, and looked for the CRUD controllers where the core no
longer keeps them); neither was registered.

### Fixed: the user.* events have their listener again

`Base\EntitySubscriber\UserSubscriber` was registered by no configuration since the back office
left the core (80088b2d): a registration asked no verification, an approval was not confirmed, an
account closed was not said goodbye to. It is registered (config/services/core.php), and its calls
are those of the notifier (`sendUserWelcomeBack($user)` had no such method without its token, and
each notification went out twice); it reads the token storage's token only when there is one (a
command, a worker), and revokes an approval request at the next flush (revoked inside the flush
that raised the event, the token was written with no user: `userToken.user_id` is NOT NULL). See
docs/50-reference/events.md. Test: tests/Subscriber/UserSubscriberTest.php.

### Fixed: the options read under their declared names

`base.user.register.autoapprove` approves a new account (SecuritySubscriber read
`base.user.autoapprove`, and the `register` node had no defaults when a site configured nothing
under it); `base.site.maintenance.lockpath`'s lock file puts the site under maintenance
(MaintenanceProvider read `base.maintenance.lockpath`); the maintenance and launch pages send a
visitor back to `base.site.index` (they read `base.site.homepage`: a null route);
`site.maintenance.redirect_on_deny` defaults to `security_maintenance`, the core's page (the route
`maintenance` it named does not exist); the error page's support address falls back on
`notifier.technical_recipient.email` (it read `base.notifier.technical_support`); cache:clear's
fallback warnings read `base.router.use_fallbacks`. Test: tests/DependencyInjection/ParametersReadAreDeclaredTest.php
(every `base.*` key read in src/ and templates/ is declared; five left, read with a fallback, listed there).

### Fixed: `user:notifications` runs

Every run stopped on "Undefined property $baseService"; its finders were given a group-by where
they take an order; `base.user.notifications.expiry` was null unless configured, and its default
`+30d` read "d" as a time zone (now `+30 days`). `--expiry` sets the age for one run and
`--clear-all` erases every read notification, whatever its age.

### Removed: `user:notifications`' `--impersonator`, `--event`, `--pretty`, `--statusCode`

Declared and read by nothing (the options of another command).

### Changed: the package is omnisense/omnibase

Omniscient is now Omnisense (2026-10-26): the package is `omnisense/omnibase`, installed in
`vendor/omnisense/omnibase`, its repository `glitchr-omnisense/omnibase`; it requires
`omnisense/omnibridge` and `omnisense/omnidrive` (the families' cores took the same vendor). A
`replace` keeps `omniscient/omnibase` resolving, beside `glitchr/omnibase`: an application renames
its require (and any path it wrote under `vendor/omniscient/omnibase`) at its next update.

### Removed: the former names of A1 and A2

Removed at omnibase 4's final pass, once no site and no bundle under the workspace named them (the last
callers, omnibase/blog's `BlogScreens` and lesringostars' screens, now name `Omnibridge\Contract\Urls`):
`src/Resources/Aliases.php` and its `autoload.files` line; `Base\Contract\{Urls, Translations, Locales,
Access, Sessions, Tokens, Files, Routes, Environment, SystemClock}` and `Base\Exception\MissingContractException`
(name `Omnibridge\Contract\*`, `Omnibridge\SystemClock`, `Omnibridge\Exception\MissingContractException`);
`Base\Bridge\Symfony\{RouterUrls, TranslatorTranslations, KernelEnvironment, SecurityAccess, SecuritySessions,
CsrfTokens, FileLinks}` (name omnibridge/symfony's `Omnibridge\Symfony\*`); their service ids, and
`ContractsPass::FORMER` is empty. omnibridge/twig's `Omnibridge\Twig\Contract\{Urls, Translations}` go with
them. Test: `tests/Contract/FormerNamesTest` (none of them is declared).

### Fixed: no empty segment in a generated address

`AdvancedUrlGenerator::generate()` gave `/boutique//wishlist` for `path("app_wishlist", {store: null})` where the
router does not check requirements (`strict_requirements: null`, the sites' dev and test): until 4bf08f94,
`sanitize_url()` cleaned the first address of a route (with the slash before the query it also changed), and every
later one already had the empty segment. An empty segment of the path is now dropped every time
(`AdvancedUrlGenerator::withoutEmptySegments()`: scheme, host, query, fragment and a trailing slash untouched, a
network path keeps its host). Test: `tests/Routing/EmptySegmentTest` (the harness's `demo_shelf` routes,
`docker/app/src/Controller/ShelfController.php`).

### Changed: the bell's marks, the second factor's enrolment and the cookies' domain on every host

What a sign-in still did only under Symfony (audit "lesringostars face au master plan", R29), written once:
- actions (`src/Controller/Action`, read by both hosts' routers): `Notification\{Read, Delete}`
  (`/notifications/read`, `/notifications/delete`, formerly `NotificationController::MarkRead()`, `Delete()`:
  the bell's token in the JSON body checked through `Omnibridge\Contract\Tokens::isValid()`),
  `Settings\{Enrolment, EnrolmentSkip, EmailCodeToggle}` (`/settings/security-required`, its `/skip`,
  `/settings/2fa/email`, formerly `UserSettingsController::Enrolment()`, `EnrolmentSkip()`,
  `TwoFactorAuthentication_ToggleEmail()`; a visitor sent to sign in; the account told through
  `Settings\Notice`: a host's own notice, else the Notifier); an action is given `array $body` (what a POST
  sent: its JSON object, else its form fields) as it is given `array $query` (`ActionArguments`);
- `Base\Auth\EnrolmentPrompt`: when a signed-in account is sent to the enrolment (the offer after a new
  device, a required second factor until "not now"), `SecurityEnrolmentSubscriber`'s rule, which omnibridge/laravel
  asks too;
- `Base\Routing\CookieDomain`: the domain of the site's cookies (the browser's PHPUSERID),
  `AdvancedRouter::getDomain()`'s rule, framework-free;
- `Redirect` carries more headers (a cookie: the enrolment's "no thanks");
- `AliasTrait`: add*() and remove*() of an alias's singular reach their column (`addGallery()` of a property
  `#[Alias(column: "children")] $galleries`, `addReply()` of a class alias "replies"; they failed at run time),
  and `EntityAccessorsExtension` knows them for PHPStan.

### Changed: the core without direct Doctrine on lesringostars' path

The classes an application's pages, sign-in and crontab reach (measured on lesringostars: the lines its
suites ran, audit "lesringostars face au master plan", R28) ask their repositories by interfaces, Doctrine's
repositories behind - the same finders, so the same SQL and the same result cache:
- new interfaces (`Base\Repository\Aliases::INTERFACES`, aliased by Symfony, bound by omnibridge/laravel):
  `ThreadTranslations` (`search()`, the site-wide search's query, formerly written in
  `ThreadSearchController`), `Extension\{TrashBalls, Revisions}`, `Layout\{Widgets, Widget\Slots, ImageCrops,
  Redirections, Semantics}`, `Thread\{Likes, Comments, Mentions}`, `User\{Connections, Passkeys, Tokens,
  PushSubscriptions}`, `Analytics\{PageViews, Visits, PageVisits}`, `Hours\{ScopedWeeks, SpecialDays,
  WeekDays}`; `Users`, `Threads` and `Layout\Settings` answer what their callers asked by name
  (`oneByEmail()`, `oneById()`, `byIds()`, `byRoles()`, `byUsernameContaining()`, `activeWithin()`,
  `byStateUncached()`, `oneBySlug($slug, false)`, `under()`, `oneByInsensitivePath()`);
- `Base\Database\Repository\Repositories`: the entities of a class named at run time (`of()`, `byIds()`,
  `oneById()`), `Base\Bridge\Doctrine\DoctrineRepositories` over the entity manager (a plain
  `EntityRepository` behind `DoctrineEntities`) - `SelectType`, `MentionEnhancer`, `uploader:entities` and
  the search's sort by hierarchy use it;
- constructors: `ThreadSearchController(FormProxyInterface, ThreadTranslations, Repositories)`,
  `UserProfileController(Users, ...)`, `UserSearchController(..., Users)`, `LoginFormAuthenticator(Referrer,
  Users, ...)`, `WidgetProvider(Widgets, Slots)`, `TrashManager(EntityManager, TrashBalls)`,
  `VersionManager(EntityManager, Revisions, int)`, `SelectType`'s second argument `Repositories`,
  `InAppChannel(EntityManager, Users)`, `MentionEnhancer(Mentions, Users, Repositories, Obfuscator)`
  (`mentions()`; `getRepository()` deprecated); `BaseNotifier::setUsers()`;
- `Base\Service\Storages`: omnibase's named storages over omniscient/omnidrive (a drive by name, the public
  paths), with no Flysystem type: what every service of the core type-hints in place of the deprecated
  `FlysystemInterface`; `Base\Service\Flysystem` implements both. `Uploader` reads a stored file through
  the storage's drive;
- `Base\Service\Locator` keeps what `BaseTrait`'s static accessors resolve (framework-free);
  `BaseService` extends it, so an accessor no longer loads the Symfony facade;
- `Base\Bridge\PHPStan\EntityAccessorsExtension` (extension.neon, audit R21b): an `#[Alias]` accessor and
  a translation's method called on its entity are known to PHPStan, with the signature of the method
  they end in.

### Added: actions written once for every host

A plain class whose `#[Route]` (symfony/routing's attribute, which a router reads and the class
does not load) declares its route, and whose `__invoke` returns a result of omnibase's (a `View`, a
`Template`, `Json`, a `Shell`, a `File`, or null for no such page) - the routes and the answers of
both hosts from one declaration (audit "lesringostars face au master plan", R27):
- `Base\Bridge\Symfony\ActionResults` (`kernel.view`): Symfony's router reads the action's route, the
  `ScreenResponder` answers its result, null from an action declared to return one is a 404;
  `ActionArguments` gives `array $query` the query string's values, as omnibridge/laravel does;
- the bell's payload (`/notifications/latest`) is `Base\Controller\Action\Notification\Latest`
  (formerly `NotificationController::Latest()`), a widget's page (`/page/{slug}`)
  `Base\Controller\Action\Widget\Page` (formerly `WidgetController::Page()`): same names, same
  answers; under `src/Controller`, so the routes import of every omnibase site
  (`@BaseBundle/Controller`) reads them;
- an invokable controller (`"Class"`, its `__invoke`) is read as a `"Class::method"` one by the
  `IconProvider`, the breadcrumb, `IsGrantedSubscriber` and `omnibase:icon:controllers`;
- `Base\Repository\Aliases::INTERFACES`: the core's repositories by their interfaces, the one list
  Symfony's container aliases and omnibridge/laravel binds;
- `Base\Screen\Redirect`: what an action hands back to send the client elsewhere (Location, 302 by
  default), answered by `Negotiation` and both hosts' responders.

### Changed: omnibase's Twig extensions built on contracts, for any host

What a page asks of omnibase's Twig no longer needs Symfony (audit "lesringostars face au master plan",
R24); omnibridge/laravel registers the same classes. Same names, same output under Symfony:
- `PictureTwigExtension` (MediaService, FileService): `image()`, `imageset()`, `video()`, `audio()`,
  `embed_base64`, `|thumbnail` and its variants, `|crop`, `|soundify`, `|vidify`, `|imagify`,
  `|mimetype`, `|extensions`, `|filesize`, `|public`, `|downloadable`, `|obfuscate_file`,
  `|obfuscate_image`, `|lightbox` - formerly `MediaTwigExtension`'s, which keeps what needs the router;
- `LinkTwigExtension`: `|urlify`, `|linkify` (whether an address is the page's own: a closure,
  `MediaTwigExtension::links()` under Symfony);
- `IconTwigExtension` over `Base\Service\Icons` (`|iconify`'s rule, `IconProvider::icons()`);
- `EnumTwigExtension` (the Translations contract): `enum()`, `|colorify` (from `FunctionTwigExtension`),
  `|trans_enum`, `|trans_enumExists` (from `TranslatorTwigExtension`), over
  `Base\Service\Model\EnumNouns`, which `Translator::transEnum()` now answers by;
- `TextsTwigExtension` (the Texts contract): `|wysiwyg`, `|wysiwyg_toc` (from `HtmlTwigExtension`);
- `NotificationTwigExtension` takes the Access and Tokens contracts (no longer Security and the CSRF
  manager), its WebPushService optional; `DemoTwigExtension` its registry and factory optional;
- `|at` and `|emoji_only` are omnibridge/twig's `HelperExtension`'s;
- `Base\Routing\AdminRouteNames` (omnibase/admin's names of its routes, which its registry uses) and
  `Base\Twig\Variable\AdminLinks` (`app.admin.crudify()` on the Routes and Urls contracts).

### Fixed: a label a language gives as its key is that language's word

`Base\Service\Translator` took a humanised label translated to itself ("Clicks": "Clicks" in an
English catalogue) for a missing key and gave the default language's word ("Clics"): an English back
office showed French column headers. A text the language's own catalogues define (the locale's and its
fallbacks short of the default language, ICU or not) is kept; one they lack still falls back to the
default language (`definedBeforeDefault()`).

### Changed: the legacy administration's welcome from the catalogue

`templates/admin-legacy/layout.html.twig` prints `'@admin.welcome'|trans` - "Bienvenue sur l'interface
d'administration !", "Welcome to the administration interface!" - in place of a French sentence that
named one site ("… des Ringo Stars !").

### Changed: the storages read through omniscient/omnidrive

`Base\Service\Flysystem` reads and writes its storages as omniscient/omnidrive's drives
(`Omnidrive\Drives`): under Symfony the `Omnidrive\Drives` service omnibridge/symfony registers over
the services tagged `flysystem.storage` (flysystem-bundle's storages, or the core's local ones without
it), under Laravel omnibridge/laravel's disks; a PSR-11 container of Flysystem operators is still
accepted (read as `Omnidrive\Flysystem\OperatorDrives`). Its read, write, delete, fileExists, mkdir and
mimeType go through `Omnidrive\Drive`, `isRemote()` is what the drive says of itself
(`capabilities()->local`); `getDrives()` and `getDrive()` are new. `FlysystemInterface`,
`getOperator()`, `getAdapter()`, `getDefaultStorage()` and `createStorage()` are deprecated, kept for one
release: new code type-hints `Omnidrive\Drives`. The core requires omniscient/omnidrive 1.x-dev (an
application names it in its own composer.json, as the other branch releases).

### Changed: omniauth's new names, and the TOTP secrets sealed at rest
- omniauth's family took its final shape (as omnishield's): the identity providers' mechanics
  are omniscient/omniauth's own (`Omniauth\OAuth\`, the same names), omniauth/passkey is
  **omniauth/webauthn** (`Omniauth\WebAuthn\`), omniauth/totp is **omniauth/otp**
  (`Omniauth\Otp\`); the former names are aliases for one release. `Base\Auth\UserPasskeys`,
  `config/services/auth.php` and the tests use the new ones; `suggest`, `require-dev`, the
  harness image and the CI's consumer install name omniauth/webauthn, omniauth/otp and
  omniauth/google (omniauth/oauth is gone).
- `UserSettingsController`'s TOTP enrolment keeps the confirmed secret through omniauth's
  `TotpSecrets` when omniauth is installed, and `qr_code_totp` draws the QR code of the secret
  that store opens: with `omniauth.yaml`'s `totp.secret_key`, the secret is sealed at rest
  (omniauth/otp's `SodiumSecretBox`, decision QA5). Without omniauth, as before.

### Fixed: the finder's YoungerThan and OlderThan compare with now moved by their age
- `findBy…YoungerThan($age)`, `countBy…YoungerThan()`, `…OlderThan()` and their `EqualTo`
  forms (an age in seconds or `"+2 days"`) gave `round_datetime(now, $age)` as their bound
  since 2023-01: now rounded to the nearest multiple of the age, an instant up to half the age
  before or after now. YoungerThan matched, at best, a fraction of the age, and nothing when the
  bound fell after now (`Threads::countByStatePublishedYoungerThan(…, 48 hours)` answered 0:
  lesringostars' menu never marked a recent article; AnalyticsSubscriber's online users were
  mostly none); OlderThan did not wait the age (`user:notification --clear` and
  `base.user.notifications.expiry`). The bound is now minus the age (Within:
  now plus its offset, as before 2023), rounded to the minute so a `cache…` finder keeps its
  key for that minute. A bound on a field of an immutable date type is bound with the mutable
  type of the same name (DBAL refused a `DateTime` there). `tests/Database/Repository/DateFinderTest`.

### Fixed: six catalogues read alike by every YAML parser
- A double-quoted message over several lines had a line at its key's own indentation
  (`fields+intl-icu.fr.yaml`'s `password.min_length`, line 80; `emails+intl-icu.fr.yaml`'s
  `adminApprovalConfirm.content`; `forms+intl-icu.{en,fr}.yaml`'s
  `resetPassword.descriptionRequest`; `validators+intl-icu.en.yaml`'s `password.min_length`;
  `validators+intl-icu.fr.yaml`'s `file.mime_type` and `password.min_length`). YAML 1.2 wants
  the continuation lines of a flow scalar in a block mapping deeper than its key: Symfony's
  YAML is lenient and read the message, a parser that follows the specification (JavaScript's
  `yaml`, which lesringostars-react reads the catalogues with) refused the whole file. Those
  lines are indented one step deeper; Symfony reads every catalogue exactly as before (the 28
  files parsed before and after, equal), and `yaml` now reads the six as Symfony does.
  `tests/Translation/QuotedScalarIndentationTest` keeps it so.

### Changed
- **The repositories behind interfaces** (audit "lesringostars face au master plan", step R15;
  Marco's bridge principle, the other half of R14): what a site's code and the core type-hint
  says nothing of the ORM - arrays of criteria and of order, the entities, lists of them,
  integers; no `QueryBuilder`, `Query`, `Criteria` or Doctrine `Collection` -, and Doctrine's
  repository answers it. `Base\Database\Repository\Entities` is what every repository answers
  (`find()`, `findAll()`, `findBy()`, `findOneBy()`, `count()`, `persist()`, `remove()`,
  `flush()`: `ServiceEntityRepository` implements it); `Base\Repository\Threads` what a site
  asks of its threads (the one of a slug, of a parent, of a state, the previous and the next,
  those published within a delay...), `Thread\Tags`, `Users`, `User\Notifications`,
  `Layout\Settings`. Each method is answered by the finder call its callers made by name
  before (`oneBySlugAndState()` is `cacheOneBySlugWithRouteAndState()`...): the same query, the
  same cache. Under Symfony each interface is an alias of the repository service
  (`config/services/database.php`; `Users` the application's `App\Repository\UserRepository`).
  `NotificationController` and `NotificationTwigExtension` take `Notifications`.
  `RepositoryInterfacesTest`. Foundation 826 → 832. See
  [docs/20-architecture/entities-and-repositories.md](docs/20-architecture/entities-and-repositories.md).
- **The data structure described outside Doctrine** (audit "lesringostars face au master plan",
  step R14; Marco's bridge principle): `Base\Database\Schema\` writes omnibase's own rules on
  the description omniscient/omnibridge builds without any ORM (`Omnibridge\Schema`) - the
  discriminators (`DiscriminatorRule`: the maps under class names, `#[DiscriminatorEntry]`'s
  values), the vaults, the order columns, the translations (`TranslationRule`: a translation's
  inheritance, `translations`/`translatable`, `locale`, `<table>_unique_translation`), the
  targets resolved, the extensions' index - and `Describer::describe($directories)` builds an
  application's description from its entities' folders. Doctrine reads the same declarations:
  `DoctrineConfigurationPass` and `Mapping::register()` wrap the application's driver in
  omnibridge/doctrine's `DeclarationDriver` before `MappingDriver`. `SchemaDescriptionTest`:
  the description built without Doctrine is `MetadataDescription`'s of the entity manager,
  entity by entity and table by table (the harness: 202 classes), and on MySQL the database's
  own `information_schema`. Foundation 818 → 826. See
  [docs/20-architecture/schema.md](docs/20-architecture/schema.md).
- **omniauth signs in, where an application turns it on** (plan "omniauth", step AU7):
  `config/services/auth.php`, where omniscient/omniauth is installed, binds each of omniauth's
  stores by its contract's name to `Base\Auth\`'s, omniauth's `Sender` to
  `Base\Auth\NotifierSender` (the sign-in link and the e-mailed code by e-mail alone:
  `Notifier::loginLink()`, `Notifier::emailCode()`, the `@emails.emailCode.*` texts), decorates
  omniauth's second-factor rule with `Base\Auth\Policy` (and its `Revoking` listener), offers
  `Base\Auth\PolicyLinking` to omniauth/oauth, and registers the listeners of omniauth's events
  (`TrackConnections` before `WarnNewDevice`, `UpdateAccount`, `WelcomeBack`, `GuardSignIn`;
  their Symfony readings in `Base\Bridge\Symfony\AuthListeners`). `BaseBundle` registers
  omnibridge/symfony's `OmniauthExtension`: `config/packages/omniauth.yaml` wires it all, nothing
  without it. `SignInGuard`, `ConnectionSubscriber` and `NewDeviceSubscriber` leave a sign-in
  omniauth decided (its passport's `OmniauthBadge`, its refusal) to their listener of omniauth's
  events. `SecurityController::LoginTokenRequest()` issues the links through omniauth when its
  link is mounted (15 minutes, once, hashed, a new one at each request). Foundation 816 → 817.
  Then, from lesringostars' switch: in the `demo` environment `Base\Auth\DemoPasswords` gives the
  back office's accounts their secrets where omniauth checks the password (the database's never
  serves), as `DemoBackOfficeSubscriber` does under Security; the welcome-back e-mail is
  `Notifier::userWelcomeBack()`'s (`WelcomeBack` and `UserChecker` named
  `@Base/security/email/account_welcomeBack.html.twig`, which never existed); the demonstration's
  tests sign in through the form with its CSRF token. Foundation 818.
- **English names for the French ones** (Marco, 2026-10-21: code and routes in English, but
  the routes localized in French): the Foundation (`tests/Foundation.php`, formerly
  `tests/Fondation.php`, read by `docker/bin/bare` and `BareTest`); `is_multiline()`, with
  `is_multiligne()` kept as a deprecated alias; `EntityHydrator::resetHydrationMapping()` and
  `$hydrationMapping` (protected); `UserSettingsController::TwoFactorAuthentication*()` (the
  routes `user_settings_2fa*`, their names and paths, unchanged); `SingletonTrait::getInstance()`'s
  `$instantiateIfNotFound`; `user:notifications`'s `--statusCode` (read by nothing).
- **`require` is the Foundation**: PHP and its extensions, Doctrine (orm, dbal, collections,
  persistence, event-manager, common), Twig and its extras, the standalone Symfony components
  (decision D1), the PSRs, league/flysystem, omnibridge/doctrine and omnibridge/twig, the
  libraries. Added, because the Foundation loads them and they only came through what left:
  `web-auth/webauthn-lib` (`Base\Entity\User\Passkey`), `symfony/error-handler`,
  `symfony/var-dumper` (from `require-dev`), `doctrine/common`, `symfony/options-resolver`,
  `property-access`, `finder`, `filesystem`, `yaml`, `password-hasher`; and, named for what
  the code imports, doctrine/{dbal, collections, persistence, event-manager}, psr/{log, cache,
  simple-cache}, symfony/{cache, mime, string, event-dispatcher} and the contracts,
  `composer-runtime-api`.
- **`ext-bcmath` is required** (plan "omnibase agnostique", step P4): `src/Resources/Functions.php`,
  loaded by Composer's `files` autoload with the first class, throws "bcmath is not installed"
  without it, and did not say so - a host whose image lacks it (lesringostars-laravel's) could
  only leave the functions unloaded. Composer now refuses the install there instead.
- **The framework left `require`** for **omnibridge/symfony**: doctrine/doctrine-bundle,
  symfony/{config, dependency-injection, dotenv, form, framework-bundle, messenger,
  monolog-bundle, security-bundle, twig-bundle}, twig/extra-bundle (and, through them,
  http-kernel, http-foundation, routing, security-*, console, twig-bridge, doctrine-bridge).
  The core does not require omnibridge/symfony (a Laravel application would receive Symfony):
  it is suggested, and in `require-dev`.
- **The bundles the core works with are suggested**, no longer required: scheb/2fa,
  league/flysystem-bundle, hwi/oauth-bundle, web-auth/webauthn-symfony-bundle,
  api-platform/symfony, api-platform/doctrine-orm, symfony/webpack-encore-bundle,
  symfony/ux-twig-component, glitchr/ux-google, glitchr/well-known, nelmio/cors-bundle,
  nelmio/security-bundle. The ones the core's code names are in `require-dev` with
  omnibridge/symfony, so that the core's own suite resolves (`Dockerfile.test`).
- **A Symfony application without one of them still compiles** where a fallback costs
  nothing: API Platform's decoration (`OverriddenResourceNameCollectionFactory`) is ignored
  without the service it decorates; `ga.service` (glitchr/ux-google) and Encore's
  `webpack_encore.entrypoint_lookup[_default]` are given as null (a `?` before a service id
  in `config/services/core.php`); `Base\Security\UserProvider` no longer implements HWI's
  `OAuthAwareUserProviderInterface` - the service of that id is a
  `Base\Security\OAuthUserProvider` (new: the same class, declared OAuth-aware) when
  hwi/oauth-bundle is installed; the `twig_component` defaults are prepended only when its
  extension is registered. Passkeys' repositories were already passed by without
  webauthn-symfony-bundle, Encore's Twig functions are the core's own.
- **Two bundles stay required de facto** under Symfony: **scheb/2fa** (`Base\Entity\User`
  implements its `Scheb\TwoFactorBundle\Model\*` interfaces) and
  **league/flysystem-bundle** (`Base\Service\Flysystem`, the `flysystem` service, extends
  its `LazyFactory` and is built from its storages).
- **No bundle is required de facto any more** (step C7b). `Base\Entity\User` implements
  `Base\Contract\SecurityUser` and `Base\Contract\TwoFactorUser` instead of Security's
  `UserInterface`, `PasswordAuthenticatedUserInterface` and scheb/2fa's four model interfaces:
  where those packages are installed, the contracts are aliases of
  `Base\Bridge\Symfony\SecurityUser` and `TwoFactorUser`, which extend exactly those
  interfaces (a site's `App\Entity\User` is unchanged: same class, table, mapping, methods,
  and `instanceof` answers as before); elsewhere, empty interfaces. Installed means declared,
  nothing else decides (`Base\Contract\Framework::declares()`; step C7c removed the
  `OMNIBASE_FRAMEWORK` variable and `Framework::without()` C7b had added: a vendor without
  the framework is how a host is without it). `getTotpAuthenticationConfiguration()` answers null without
  scheb/2fa. `Base\Service\Flysystem` no longer extends league/flysystem-bundle's
  `LazyFactory`: its constructor takes a PSR-11 container of storages, the service `flysystem`
  (and `FlysystemInterface`) is built from `tagged_locator('flysystem.storage', 'storage')` -
  what the bundle tags each configured storage with, the same storages its own lazy factory
  reads - and, without the bundle, `FlysystemStoragesPass` registers omnibase's local storages
  (`local.cache`, `local.uploads`, `local.wysiwyg` and their `.public`). scheb/2fa and
  league/flysystem-bundle stay suggested and in `require-dev`, their texts no longer saying
  "required de facto". Tests: `SuggestedBundlesTest` leaves out `SchebTwoFactorBundle` and
  `FlysystemBundle` too (the `flysystem` service on the local storages), the user implements
  the six interfaces under Symfony, `Flysystem` built from a plain locator; `BareTest`: the
  user loads nothing of the framework where it is not installed (`var/bare/vendor`, step C7c),
  and on the run's vendor, where it is, names the six. Foundation 758 -> 763 (`Base\Entity\User`,
  `Base\Service\Flysystem`, `Base\Contract\{Framework, SecurityUser, TwoFactorUser}`).
- `extra.branch-alias`: `dev-4.x` is `4.0.x-dev`.
- **PHP 8.4 at least** (`"php": "^8.4"`, from `^8.2`): doctrine/collections 3.1 requires it, so `^8.2` announced
  installs that could never resolve. The running sites are on 8.4; the harness runs 8.4.
- **Doctrine: on suit la dernière majeure, pas de maintenance parallèle.** `doctrine/orm`
  `^3.4` and `doctrine/dbal` `^4.2` (omnibridge/doctrine's floors, which the core requires:
  `^2.15|^3.0` and `^3.6|^4.0` said what could never install), `doctrine/collections` `^3.1` (3.1 requires PHP 8.4),
  `doctrine/event-manager` `^2.0`, `doctrine/persistence` `^4.0`, `doctrine/common` `^3.0`:
  the current major of each, open upwards, no older branch, no upper bound, no pinned
  version, Doctrine as published (no patch, no fork).
- **doctrine/dbal as published; DC2Type comments no longer written** (step C6b).
  `glitchr/doctrine-dc2type` left `require` and `allow-plugins`: that Composer plugin rewrote
  DBAL's schema managers so that a column's type was read from its `(DC2Type:...)` comment,
  which `Base\DatabaseSubscriber\EnumSubscriber` wrote on every ENUM and SET column with its
  values. Both are gone (EnumSubscriber and `DoctrineEnumSubscriberPass` removed). In their
  place, DBAL's own means: an ENUM column is read as DBAL's `enum` type with its values (DBAL
  4.2), `PlatformMiddleware` no longer maps `enum` to `string` and `set` to `json`; on MySQL
  and MariaDB **`Base\Bridge\Doctrine\SchemaManagerFactory`** gives
  **`Base\Bridge\Doctrine\MySQLSchemaManager`**, which reads a SET column as
  **`Base\Database\Type\MySQLSetType`** with its values (DBAL reads SET as a LONGTEXT
  `simple_array`) and leaves the `(DC2Type:...)` of a comment out (with the values
  EnumSubscriber wrote after it). Symfony: it decorates DoctrineBundle's
  `doctrine.dbal.default_schema_manager_factory`; by hand:
  `$configuration->setSchemaManagerFactory(new SchemaManagerFactory())`. A value added to an
  enumeration is still a column altered: the values compared are the database's. `EnumType`
  and `SetType` write `ENUM(...)`/`SET(...)` as DBAL quotes them, and `CLOB` on SQLite (what
  SQLite's `TEXT` is read back as). **A database that still has the old comments needs no
  migration**: they are left out on reading, so `doctrine:schema:validate` and
  `doctrine:migrations:diff` find nothing (an ALTER made later for another reason drops them).
  `doctrine:array:upgrade` reads the comments from `information_schema` itself.
  Tests: `tests/Bridge/Doctrine/SchemaManagerTest` (MySQL rows read and compared, a SQLite
  table created, read back, unchanged); `EnumColumnTest` keeps its icon cases.
- Tests: `SuggestedBundlesTest` - `require` holds neither the framework nor a bundle, the
  Foundation's additions are there, the suggested bundles the code names are suggested and in
  `require-dev`; `UserProvider` names no interface of HWI; and the host application booted
  without the nine bundles the core can do without (`Fixtures\WithoutSuggestedBundlesKernel`:
  their configuration and route files left out with them) compiles, drops API Platform's
  decoration, gives `ga.service` as null and answers `/login` (200, Encore's functions the
  core's own) and `/`. `bare` on a `composer install --no-dev` of the core alone: the
  Foundation (758 classes) loads nothing of the framework, and none of it is installed but
  `symfony/console` (doctrine/orm's).
- **`bare` measures where the framework is not installed** (step C7c): on `var/bare/vendor`,
  the core installed alone by `composer install --no-dev` (`bare --install`, its autoload
  pointed at the checkout's `src/`, made again when `composer.json` changes; `demo.sh` runs
  it before `bare` and the core's suite), no longer on the harness's vendor with a variable
  saying the framework is absent. `BareTest` measures the Foundation and the user there (and
  checks that vendor holds no package of the framework, `symfony/console` aside), skipped
  where it was not made (an application's suite); the user on the run's vendor declares the
  six interfaces.
- The harness: `docker/Dockerfile` requires `omniscient/omnibase:4.x-dev as 4.0.x-dev`,
  omnibridge/symfony and every bundle `docker/app/config/bundles.php` registers (`BUNDLES`);
  the root `Dockerfile` (the small demo) omnibridge/symfony and its bundles.
- The `omnibase/*` bundles accept the core 4.0 (`3.0.*-dev || 4.0.*-dev`) and require
  omnibridge/symfony themselves: they are Symfony bundles, and used the framework through
  the core's requirements.

### Changed: an action's answer negotiated without the framework (step R10)
- **`Base\Screen\Negotiation`**: the body of `Base\Bridge\Symfony\ScreenResponder` moved there as
  it was, framework-free (audit "lesringostars face au master plan", R10: a site's actions written
  once for Symfony and Laravel): a `View` within a front end (omnibridge/react's page in JSON, 409
  for another version), in JSON alone without omnibridge/react, or rendered with `frame` and
  `screen`, with their caching (private and varying on Cookie for a visitor, no-store for a
  Confidential screen). With what an action written once hands back: `Template` (a template and its
  data; `json`: a client preferring JSON gets the data, both answers varying on Accept; `alone`: the
  page is its own markup only), `Json` (data whose `Template`s and `Fragments` the host renders,
  encoded as Symfony's JsonResponse encodes them, under every host), `Shell` (omnibridge/react's
  protocol: the shell on a first visit, the page in JSON, 409), `File`; the client's request read as
  a `Visit` (its address, its Accept - the first type naming a format decides, as Symfony's
  getPreferredFormat() -, omnibridge/react's headers), the caching an action asks as a `Cache`, the
  result as an `Answer`. The host renders the templates by a closure (`Negotiation::PAGE`, `PART`:
  a value of a JSON answer, `ALONE`).
- **`ScreenResponder` is Symfony's adapter of it**: `respond()` takes any of these, or null (no such
  page: a 404), reads the Visit off the Request, makes a Response, a JsonResponse (FlashBagSubscriber
  still folds the session's messages into one) or a BinaryFileResponse of the Answer, and sets
  `_omnibase_inject: false` for a page alone. Unchanged for a View: `respond()`, `legacy()`,
  `props()`, `guard()`. omnibridge/laravel's `Screen\Responder` is Laravel's adapter.
- Foundation 807 -> 816. `NegotiationTest` (each result, the Accept rule, the Shell's protocol),
  `ScreenResponderTest` (a View's answers as before; a Template alone, JSON, a File, no result).

### Added: omniauth's stores on omnibase's tables (plan "omniauth", step AU4)
- **`Base\Auth\`**, omniscient/omniauth's contracts filled on the tables the core has, over
  Doctrine's entity manager (Symfony's, or the one a Laravel host builds on the same mapping),
  each write flushed at once: `UserAccounts` (by address, then by the application's identifier
  field - lesringostars' username -; the id as the handle; `check()` is `UserChecker`'s rule,
  reasons `restricted`, `banned`, `locked`, `disabled`, a disabled account with a valid
  "welcome-back" token enabled again), `UserPasswords` (`user.password`), `UserLinkTokens`
  (`userToken` "login-token" holding the link's SHA-256 - QA4 -, claimed once by an UPDATE then
  removed; `PlainLinkTokens`: the links of before, in clear, until their deadline, never a value
  shaped like a hash), `UserTotpSecrets` (`user.secret`), `UserEmailCodes`
  (`user.emailAuthEnabled`, `user.emailAuthCode` holding the code's keyed hash - QA5 -, null once
  used), `UserBackupCodes` (`user.backupCodes`), `UserTrustedVersion`
  (`user.trustedTokenVersion`), `UserPasskeys` (omniauth/passkey's records over
  `Base\Entity\User\Passkey`: base64 in the record, bytes in the entity), `UserIdentities`
  (omniauth/oauth's identities, `userIdentity` below). A store given an account the entity
  manager no longer holds writes its managed copy.
- **`Base\Auth\Policy`**: `SecurityPolicy` around omniauth's second-factor rule - a
  demonstration account is never asked for one, `needsEnrolment()` for the host's prompt,
  `onRevoking()` (omniauth's `Revoking`) refuses to turn off the last second factor of an
  account that must keep one (`two-factor-mandatory`) and any credential of a demonstration
  account (`demo-account`), `allowsEmailLinking()` never for an account with a second factor or
  an administration role; `PolicyLinking` gives that to omniauth/oauth (QA3).
- **The listeners, `Base\Auth\Listener\*`**, on omniauth's PSR-14 events, each with its
  `events()`: `GuardSignIn` (`SignInGuard`'s captcha after failures from an address; the
  captcha's answer from a closure the host gives), `TrackConnections` (`ConnectionSubscriber`'s
  connections), `WarnNewDevice` (`NewDeviceSubscriber`'s e-mail and offer; the session as
  omniauth's `State`), `UpdateAccount` (`LoginFormAuthenticator::onAuthenticationSuccess()`'s
  roles, time zone, language, kick), `WelcomeBack` (`UserChecker`'s e-mail of a disabled
  account). Nothing is wired: omnibridge/symfony (AU6) and omnibridge/laravel (AU8) mount them.
- **`Base\Entity\User\Identity`, the `userIdentity` table** (QA3): the account
  (`user_id`, `ON DELETE CASCADE`), `provider` (32), `subject` (255), `email` (180, the address
  the provider gave then), `emailVerified`, `createdAt`, `lastUsedAt`; unique
  (`provider`, `subject`) and (`user_id`, `provider`), which serves the account's lookups;
  `Base\Repository\User\IdentityRepository`. `User::$identityProvider` keeps its meaning (where
  the account came from). An application adds the table by its own migration
  (`doctrine:migrations:diff`: lesringostars' `Version20261021100000`).
- `SecurityPolicy` and `DemoAccountRegistry` ask of accounts as objects, no longer as Security's
  `UserInterface` (the former P4), and `SecurityPolicy::isAdministrator()` (ROLE_ADMIN,
  ROLE_SUPERADMIN through the hierarchy, or a role a second factor is required of).
  `SignInGuard::requiredFor()`, `failed()`, `passes()` and `field()`: its rule by address, its
  subscriber unchanged. `User::getBackupCodeHashes()`/`setBackupCodeHashes()`,
  `User::setEmailAuthCode(?string)`, `Token::setValue()`.
- composer.json: omniscient/omniauth, omniauth/{passkey, totp, oauth} suggested and in
  `require-dev`, and under `extra.omnibase.bare`, which `bare --install` adds to var/bare (the
  Foundation implements their contracts). The harness's image requires them; its `demo test`
  runs PHPUnit with a gigabyte (the suite came to the image's 512M).
- Foundation 788 -> 807. Tests (`tests/Auth`): every method of omniauth and its three packages
  signs accounts of the harness's database in through the stores (password and the account
  brought up to date; TOTP, its replay refused, a backup code, a trusted device and its version;
  the code by e-mail hashed and used once; a link hashed, once, a link of before in clear, a hash
  refused as a link; a passkey registered then signing in without a second factor, its counter;
  Google linked by its verified address then by its subject, the address and the last use kept,
  unlinked; administration accounts never linked by address; banned, locked, disabled and the
  welcome back; the last second factor kept when it is mandatory), the stores' edges (the
  identifier field, the table's two keys, a passkey's account), the listeners (the captcha after
  two failures, not at the rescue door, forgotten after a success; the connections and the offer
  of a new device). `HandBuiltEntityManagerTest`'s fixture: Identity recorded.

### Changed: the passkey described by the core (plan "omniauth", step AU1)
- **`Base\Entity\User\Passkey` no longer extends `Webauthn\CredentialRecord`**: its 13 fields
  (`publicKeyCredentialId`, `type`, `transports`, `attestationType`, `trustPath`, `aaguid`,
  `credentialPublicKey`, `userHandle`, `counter`, `otherUI`, `backupEligible`, `backupStatus`,
  `uvInitialized`) are its own attributes, under the same public names, so the same columns of
  `userPasskey`, mapped by the core instead of WebauthnBundle's XML - described once, and read the
  same by a host without the bundle (the Laravel twin; decision O6). Their values are plain: the
  credential id and the public key as bytes, the AAGUID as its text, the trust path as
  webauthn-lib normalizes it (`[]`, `["x5c" => [...]]`); the entity loads nothing of webauthn-lib.
  `Passkey::createFromCredentialRecord()` stays.
- **Three column types of the core**, `Base\Database\Type\Passkey\{Base64, Aaguid, TrustPath}`
  (`passkey_base64`, `passkey_aaguid`, `passkey_trust_path`), registered with the others
  (`Base\Bridge\Doctrine\Types::core()`, `passkey()`, and the bundle's prepended DBAL types):
  the SQL of the bundle's `base64`, `aaguid` and `trust_path` (LONGTEXT, TINYTEXT, JSON on
  MySQL) and the same text written and read - no migration, the rows written by the bundle's
  types are read as they are, and the other way round.
- **`PasskeyRepository` and `WebauthnUserEntityRepository` declare the bundle's interfaces through
  `Base\Contract\PasskeyRecords` and `PasskeyUsers`** (the pattern of R2): aliases of
  `Base\Bridge\Symfony\PasskeyRecords` (`CredentialRecordRepositoryInterface`,
  `CanSaveCredentialRecord`) and `PasskeyUsers` (`PublicKeyCredentialUserEntityRepositoryInterface`)
  where web-auth/webauthn-symfony-bundle is installed, empty interfaces elsewhere; both load
  without the bundle. The repository gives the bundle the `CredentialRecord`s it asks for, made
  from the passkeys (`credentialRecord()`), and copies onto the passkey of that credential what it
  hands back (`fill()`), or makes a new passkey of the account its handle names at a registration.
- **WebauthnBundle's own Doctrine mapping is taken out of the entity managers' chains**
  (`WebauthnMappingPass`): it mapped webauthn-lib's three classes as superclasses for an entity to
  inherit, and none does any more (no site's either). Its DBAL types stay registered.
- Fixed: **a passkey could not sign in**. The bundle looks a credential up by its raw id;
  `findOneByCredentialId()` asked omnibase's `findOneBy()`, which binds a criterion as it is, not
  through the column's type, and the raw bytes never matched their base64 text ("The credential
  ID is invalid.", measured on lesringostars). `PasskeyRepository::findOneByCredential()` compares
  the column with the id typed `passkey_base64`.
- Foundation 779 -> 786 (`PasskeyRepository`, `WebauthnUserEntityRepository`, the two contracts, the
  three types). Tests: `PasskeyTypesTest` (the bundle's SQL on MySQL, SQLite and PostgreSQL, each
  value written by one read by the other), `PasskeyRepositoryTest` (a registration becomes a
  passkey of its account, the records the bundle asks for are the passkey's 13 fields, a sign-in's
  counter saved on the same passkey, a certificate trust path, the accounts' entities),
  `BareTest` (the passkey and both repositories load without the framework; a passkey made and its
  columns written loads no class of webauthn-lib; with the bundle installed both repositories
  declare its interfaces through the contracts). `HandBuiltEntityManagerTest`'s fixture: Passkey's
  13 fields and the three types recorded.

### Changed: the image route's rule without the framework (step R6)
- **`Base\Contract\MediaRouter`**: the five questions omnibase's media rule asks a router -
  `generate()`, `getRouteMatch()`, `getRouteName()`, `getAssetUrl()`, `getUrl()` - with the
  signatures of `AdvancedRouterInterface`, which now extends it. `FileService` and `MediaService`
  take it instead of `AdvancedRouterInterface` (under Symfony `advanced_router` is passed as
  before), and `MediaService` takes the core's `Base\Service\ParameterBagInterface` instead of
  DependencyInjection's (every `parameter_bag` omnibase gives implements both).
  `FileService::getRouter()` gives it back. A host without the framework builds `MediaService` on
  its own router (audit "lesringostars face au master plan", R6: omnibridge/laravel).
- **`Base\Service\ImageRoute`**: `MediaController`'s answers (`contents`, `imageCrop`,
  `imageWebp`, `imageSvg`, `image`: the resolution of the obfuscated data, the redirection to the
  right extension and to the cacheless routes, the crops, the filters, the local cache, the
  placeholder) moved there as they were, for any host, with `ROUTES`, the image routes' names,
  paths and actions in Symfony's order. `MediaController` keeps its routes, names and actions and
  delegates; the profiler stays its own (left aside on an `ux_` route, as before). Two
  differences, both where Symfony never goes: the crops' repository may be absent (no crop then),
  and `ux_serve` on data that resolves to nothing answers 404 instead of a TypeError.
- Foundation 777 -> 779 (`Base\Contract\MediaRouter`, `Base\Service\ImageRoute`). `BareTest`
  builds `MediaService` by hand without the framework (`tests/Service/media-without-framework.php`:
  a plain router over `ImageRoute::ROUTES`, the Obfuscator, omnibase's Flysystem over local
  storages, Imagine) and makes a thumbnail's address, reads it back and makes its file;
  `ImageRouteTest`: `ROUTES` is `MediaController`'s routes, and the controller still redirects a
  thumbnail's address to its extension and serves it, the same bytes as `ImageRoute`.

### Fixed: an attribute of a package not installed no longer stops the mapping (step R4)
- `Base\Database\Mapping\NamingStrategy::classToTableName()` and `Base\Attributes\AttributeReader`
  (class, method and property attributes) instantiated every attribute of an entity, so the core's
  entities could not be mapped where a package whose attribute they carry is not installed:
  API Platform's `#[ApiResource]` on `User` and `Thread`, which the core only suggests ("Attribute
  class ApiPlatform\Metadata\ApiResource not found", under Laravel with omnibridge/laravel's
  `omnibridge.doctrine.omnibase`). Such an attribute is left aside, as PHP itself leaves it until
  it is instantiated; where the package is installed nothing changes. `NamingStrategyTest` names a
  class carrying an attribute nobody installs (audit "lesringostars face au master plan", R4).

### Changed: the users' repository loads without Security (step R2)
- **`Base\Repository\UserRepository` implements `Base\Contract\SecurityUserLoader` and
  `Base\Contract\SecurityUserProvider`** instead of doctrine-bridge's `UserLoaderInterface` and
  Security's `UserProviderInterface` and `PasswordUpgraderInterface` (audit "lesringostars face
  au master plan", R2: the last hard seam between the core and Security on the way of a Laravel
  application reading a site's `App\Repository`). Where symfony/doctrine-bridge, resp.
  symfony/security-core, is installed, each contract is an alias of `Base\Bridge\Symfony\
  SecurityUserLoader` (the loader), resp. `SecurityUserProvider` (the provider and the password
  upgrader): under Symfony the repository implements exactly what it did (a site's
  `App\Repository\UserRepository`, the firewalls' entity provider, `instanceof` unchanged);
  elsewhere empty interfaces, and the repository loads without them. Two contracts, not one: an
  interface cannot extend both the loader and the provider (`loadUserByIdentifier()` answers
  `?UserInterface` in one, `UserInterface` in the other), a class implements both. Installed
  means declared, as for `Base\Entity\User` (C7b, C7c, `Base\Contract\Framework::declares()`).
  The methods and their signatures are unchanged: PHP does not load the Security types they name
  to declare them.
- Tests: `BareTest` - the repository loads nothing of the framework where it is not installed, and
  on the run's vendor names the three interfaces, each asked by its contract;
  `SuggestedBundlesTest` - the repository implements the three under Symfony. Foundation 774 -> 777
  (`Base\Repository\UserRepository`, `Base\Contract\{SecurityUserLoader, SecurityUserProvider}`).

### Changed: the Symfony adapters of the host's contracts are omnibridge/symfony's (step A1)
- **omnibridge/symfony holds code** (`Omnibridge\Symfony\`, the mirror of omnibridge/laravel's
  `Omnibridge\Laravel\`): the adapters that answer omniscient/omnibridge's contracts with
  Symfony's own services and nothing of omnibase moved there - `Base\Bridge\Symfony\RouterUrls`
  and `TranslatorTranslations` (`Omnibridge\Symfony\RouterUrls`, `TranslatorTranslations`),
  `KernelEnvironment`, `SecurityAccess`, `SecuritySessions`, `CsrfTokens`
  (`Omnibridge\Symfony\Contract\*`), and `FileLinks` (`Omnibridge\Symfony\FileLinks`) - with
  their wiring: `config/services/core.php` imports omnibridge/symfony's `config/services.php`
  (`OmnibridgeBundle::SERVICES`) and gives `RouterUrls` and `SecuritySessions` omnibase's router
  (`advanced_router`; the remember-me cookie's domain is now a closure on its `getDomain()`);
  `ContractsPass` keeps omnibase's list (`CONTRACTS`, `FORMER`, `SERVICES`, `base.contracts`) and
  hands it to omnibridge/symfony's `DependencyInjection\ContractsPass`, which builds the locator.
- **Stay `Base\`** (they read omnibase's services or models, D3): `Base\Bridge\Symfony\{LocalizerLocales,
  FlysystemFiles, RouterRoutes, MediaPictures, ServiceMedia, WysiwygTexts, SymfonyScreenContext,
  ScreenResponder, AccountScreens, NotificationMessages, SettingBagWarmer, SecurityUser,
  TwoFactorUser}`, `Base\Service\ParameterBag` and `HotParameterBag`, `BaseBundle` and its
  extension, configuration and passes. omnibridge/symfony does not require the core: the
  direction is one way (omnibridge/symfony's README classes each, with why).
- **The former names keep working** where omnibridge/symfony is installed: each
  `Base\Bridge\Symfony\X` moved is an alias of the moved class (`class_alias`, not a subclass),
  declared with Composer's autoloader (`src/Resources/Aliases.php`); the former service ids are
  aliases of the new ones. To be removed at omnibase 4's final pass.
- **`BaseBundle` refuses to start without omnibridge/symfony**, with a message that says so (the
  core still only suggests it: a Laravel site does not want it).
- Foundation 781 -> 774: the seven moved classes left the core (a decision of the step, not a
  class that stopped loading); omnibase's own adapters stay listed. `FormerNamesTest` checks the
  moved adapters' former names.

### Changed: the host's contracts are omniscient/omnibridge's (step A2)
- **omniscient/omnibridge** (new, required): the omnibridge family's core, the contracts any host
  answers and their registry, framework-free (PHP and three PSRs). Moved there:
  `Base\Contract\{Urls, Translations, Locales, Access, Sessions, Tokens, Files, Routes, Environment}`
  as `Omnibridge\Contract\*` (Translations is omnibridge/twig's `trans()` and omnibase's `exists()`
  in one interface; `Urls` the same, `url()` included), `Base\Contract\SystemClock` as
  `Omnibridge\SystemClock`, `Base\Exception\MissingContractException` as
  `Omnibridge\Exception\MissingContractException`, and the registry: `Base\Contract\Runtime` now
  extends `Omnibridge\Runtime` (the same state) and keeps omnibase's own accessors -
  `parameters()`, `nouns()`, `media()`, `pictures()`, `texts()`, `mailer()`. Stay omnibase's:
  `Nouns`, `Media`, `Pictures`, `Texts`, `Link`, `Framework`, `SecurityUser`, `TwoFactorUser`, the
  screens' `ContextProvider`.
- **The former names keep working**: each is an alias of the moved class (`class_alias`, not a
  subclass), declared when Composer's autoloader is loaded (`src/Resources/Aliases.php`, a new
  `autoload.files` entry; each former file requires it, for a host that maps `Base\` by hand), so
  a type or an `instanceof` naming it holds before anything asks for it. omnibridge/twig's
  `Omnibridge\Twig\Contract\{Urls, Translations}` are its own aliases of the same interfaces. The
  registry resolves a former name to the interface's own: one service under either. Under Symfony
  each contract is bound under its own name (`Omnibridge\Contract\Urls` →
  `Base\Bridge\Symfony\RouterUrls`...), the former names aliasing it; `base.contracts` lists both
  (`ContractsPass::FORMER`). To be removed at omnibase 4's final pass.
- The core's own code (the Symfony adapters, the entities, the services) names the new classes.

### Changed: what the classes read through BaseTrait, on contracts (step C4b)
Names constant (`BaseTrait` and its accessors stay for the bundles and the sites); each move
compared under Symfony with the service it replaces, and run where there is no framework
(`tests/Contract/basetrait-seams-without-framework.php`, `BareTest`). See
[docs/20-architecture/contracts.md](docs/20-architecture/contracts.md).
- **What an entity is called**: `Base\Contract\Nouns` (`entity()`, `entityExists()`) and its
  rule `Base\Service\Model\EntityNouns` (with `NounVariants`, the keys tried for a noun's
  options), over `Base\Contract\Translations` - omnibridge/twig's `Translations` and
  `exists()` - and the entity namespaces of the application's bundles. It is the rule of
  `Translator::transEntity()`, `transEntityExists()` and `parseClass()`, which now answer by
  an instance over the translator (`getEntityNouns()`), the contract's service under Symfony;
  `Translator` implements `Base\Contract\Translations`, `TranslatorTranslations` too.
  `Thread::__toString()` and `Widget::__toString()` ask `Runtime::nouns()`. `NounsTest`: every
  entity of the application, five sets of options, three properties, two languages, the old
  rule copied as it was. Foundation 763 -> 767.
- **A number of seconds in words**: `Base\Service\Model\Duration::spell($seconds,
  $translations)`, `Translator::transTime()`'s words over the Translations contract (the
  `translator` chain under Symfony: the texts rewritten in the back office first);
  `transTime()` answers by it. `Thread::getPublishTimeStr()`, `Token::getRemainingTimeStr()`
  and `getThrottleTimeStr()` ask it with `Runtime::translations()`. `DurationTest`: 24 lengths
  in French and English against the old `transTime()` copied, a rewritten unit, the getters.
  Foundation 767 -> 768.
- **The image route's addresses**: `Base\Contract\Media` (`extension()`, `obfuscate()`,
  `link()`), under Symfony `Base\Bridge\Symfony\ServiceMedia` over `MediaService`;
  `Runtime::media()`. `Image::__toLink()` asks it and `Link::to()` where it asked
  `getMediaService()` and `getRouter()`; `ImageCrop::__toLink()` asks it (its dead
  `getExtensions()` call dropped). Their `$referenceType` defaults to `Link::ABSOLUTE_PATH`
  (Routing's `UrlGeneratorInterface::ABSOLUTE_PATH`, the same 1, without loading Routing).
  `MediaTest`: eleven requests on three images and on crops against their old code copied.
  Foundation 768 -> 770.
- **What the routes are beyond their addresses**: `Base\Contract\Routes` (`pattern()`, a
  route's declared path; `match()`, the route an address leads to; `icons()`, a route's
  `#[Iconize]`), under Symfony `Base\Bridge\Symfony\RouterRoutes` over `advanced_router` and
  the `IconProvider`; `Runtime::routes()`. `Semantic` and `Widget\Route` ask it for
  `getPath()`, `getRoute()` and `getRouteIcons()`; `Widget\Route`'s links go through
  `Link::to()`, as `Semantic`'s since C3e. Fixed on the way: `getRoute()` handed the router's
  `Route` object to its `?string` return type (a `TypeError` for any route found) and now
  names the route; `getRouteIcons()` without a route is null (it gave every route's icons).
  `RoutesTest`. Foundation 770 -> 772.
- **What the application runs as, and its parameters**: `Base\Contract\Environment`
  (`name()`, `isDebug()`, `secret()`), under Symfony `Base\Bridge\Symfony\KernelEnvironment`
  (`kernel.environment`, `kernel.debug`, `kernel.secret`); `Runtime::environment()`, and
  `Runtime::parameters()`: `Base\Service\ParameterBagInterface`, bound under Symfony to
  `parameter_bag` (the settings stored in the database over the container's, what
  `BaseTrait::getParameterBag()` gives; `ContractsPass::SERVICES`). `User::getActiveDelay()`,
  `getOnlineDelay()`, `isTester()`, `Token::__construct()` (default throttling, the hash salt)
  and `VaultTrait::setSecure()` ask them. `EnvironmentTest`. Foundation 772 -> 774.
- **A user signed out, and its locale**: `Base\Contract\Sessions` (`signOut()`), under
  Symfony `Base\Bridge\Symfony\SecuritySessions` (the token storage, the `REMEMBERME`
  cookie); `Runtime::sessions()`. `User::logout()` asks it (else kicks, as before);
  `User::getLocale()` and `setLocale()` without a locale of the user's take
  `Runtime::locales()->getLocale()` where they took `Localizer::__toLocale()` of the
  translator's locale - the same for every language the site speaks. `User` reads nothing
  through `BaseTrait` any more. `SessionsTest`. Foundation 774 -> 776.

### Fixed
- **The members' search** (`user_search`, `/search/user`) rendered nothing: its template was a
  copy of the threads' search (it read `threads` and `form_data.title`, and a `generic` field)
  while its controller gave `users`, and its form had no field. `UserSearchType` has a
  `username` field; the controller finds the members whose username holds the term (case
  aside, at most 50, banned and ghost accounts left out) when the application's user has a
  username, never by e-mail address (which the page would show to anyone); the template lists
  them with their avatar and a link to their profile, says when none is found, in the
  `content` block as the account's pages. `UserSearchTest`.
- **A member's profile listing a core `Thread`** failed: `Base\Entity\Thread` had no
  `__toLink()`. It answers null (no core route shows a bare thread; it is not a
  `LinkableInterface` for that), and `Accounts::recent()` lists the threads that have a page.
  The bundles' threads override it with the same signature (19 `__toLink()` in 11 bundles go
  through `getRouter()->generate($name, $parameters, $referenceType)` and can use
  `Link::to()` as they are, at C8). `RecentThreadsTest`.

### Changed: glitchr/base-plugin replaced by omnibridge/composer (step P3)
- **`require`: `omnibridge/composer` (1.x-dev) in place of `glitchr/base-plugin`**, and in
  `allow-plugins`. omnibridge/composer is a Composer plugin that knows no package: the core
  declares what it needs in `extra.omnibridge` (which replaces `extra.base-plugin`):
  - **the stubs**: `Base\Bridge\Composer\Stub\{Entity, Enum, Form, Notifier, Repository}Stub`
    (base-plugin's Cloner providers on the engine's `AbstractStub`; `Set`, which had no
    `src/Set` to read, dropped) - the same stubs, one per plain `Base\<Name>\X` the
    application does not define, now in `vendor/composer/omnibridge/stubs/<Name>/`, read by
    no autoloader. `AbstractBaseBundle::isStub()` (base-plugin's `stubs/` too) keeps a stub
    from ever passing for an application class. `StubsTest`: each stub is the alias
    `ClassAliases` declares, deterministic, none for an application's own class.
  - **one patch**, base-plugin's hook on symfony/process as a declaration
    (`process-timeout-from-env`: Process's default timeout 60 becomes null, read from
    `SYMFONY_PROCESS_TIMEOUT` in the constructor, 60 when unset) - applied after install and
    update, recorded with checksums (`vendor/composer/omnibridge/patches.json`), checked by
    `composer omnibridge:patches --check`.
- **symfony/dependency-injection as published: `Base\Service\ParameterBag` wraps
  `ContainerBag` instead of extending it.** It overrides `set()` (`HotParameterBag` `add()`)
  returning `void`, which PHP refused against `FrozenParameterBag`'s `never`
  (DependencyInjection 7 and 8): base-plugin rewrote every `never` of that file. The bag
  implements DependencyInjection's `ParameterBagInterface` and `ContainerBagInterface` itself
  (the `parameter_bag` service and its autowiring aliases are unchanged), reads through the
  wrapped `ContainerBag`, and forbids what the frozen bag forbade (`clear()`, `add()`,
  `remove()` throw its `LogicException`; `HotParameterBag::add()` still keeps what it is
  given in its hot bag). It is no longer a `FrozenParameterBag` (nothing typed that).
  `ParameterBagTest`.
- **symfony/error-handler as published.** The file links of the error pages and of the
  profiler on the host (APP_PATH) are Symfony's own: `Base\Bridge\Symfony\FileLinks`
  decorates `debug.file_link_formatter` with Symfony's `FileLinkFormatter` on a mapped format
  (`...&/srv/app/>/Users/me/site/`; the sites' `framework.ide` in omnibase's former syntax,
  `vscode://file/%%f[%%b:%env(APP_PATH)%]:%%l`, read as that mapping, so no site changes its
  configuration), and exports `SYMFONY_IDE` for the page the
  `ErrorHandler` renders before a request is handled (docs/20-architecture/errors.md).
  `Base\Inspector\FileLinkFormatter` (it extended the `@final` formatter) and
  `Base\Inspector\HtmlErrorRenderer` are removed, and with them base-plugin's two hooks on
  symfony/error-handler. Decorating `error_renderer.html` too was not needed: Symfony's HTML
  renderer takes `debug.file_link_formatter`. `FileLinksTest`. Foundation 778 -> 777.
- **Dropped with base-plugin**, its hooks for packages the core no longer needs patched:
  doctrine/orm (as published since C6), league/flysystem-bundle (the core no longer extends
  `LazyFactory`, C7b), hwi/oauth-bundle (2.5 uses DependencyInjection's `Extension`),
  symfony/polyfill-mbstring (PHP 8.4 has `mb_ucfirst`/`mb_lcfirst`), easycorp/easyadmin-bundle
  and paypal/rest-api-sdk-php (not installed: omnibase/admin, omnitrade/paypal).
- **An application's `bin/console`** keeps its line `use Base\Console\Application;`, written
  once (base-plugin rewrote it at each install).

### Changed: BaseService lazy and off the request path, BaseTrait on the contracts (step C9)
- **`Base\Service\BaseService` is a lazy service**: a ghost of the class (PHP 8.4 lazy
  objects) until one of its methods reads a property; its 23 services are built then, not
  when it is injected. `BaseServiceLazyTest`.
- **Nothing on a page's path injects it**: `Base\Twig\Variable\SiteVariable` and
  `AdminVariable` take the `SettingBagInterface` in its place, and the kernel last (the
  execution time: the kernel's start in debug, else their construction, as BaseService
  measured it); `EmailVariable` takes the router, the `SettingBagInterface` and the launcher;
  `FormTypeBootstrapExtension` the parameters; `ErrorController` the `Base\Contract\Environment`
  contract (its third argument); `MainController` and `UserSettingsController` lose an
  argument they never used. New in the Foundation: `Base\Service\Model\DevelopmentEnvironment`
  (`isDevelopment()`'s rule). The core's `getService()->isDebug()/getSalt()/isGranted()/
  isEntity()` are on the contracts; `AutovalidateController` and `AssociationFileType` no
  longer use BaseTrait. `SiteVariableTest`, `DevelopmentEnvironmentTest`. Foundation 777 -> 778.
- **BaseTrait and BaseService are kept, not deprecated**: the application's shortcuts. What a
  contract answers they ask it when the registry has it - `getParameterBag()`,
  `getEnvironment()`, `isDebug()`, `isDevelopment()`, `getSecret()`/`getSalt()`,
  `isGranted()` - so they answer without Symfony (`BareTest`), and on the ghost build
  nothing. The rule, in docs/20-architecture/contracts.md: BaseTrait to go fast in an
  application, the contracts in the core and the bundles (which add no new use of it).

### Changed: the parameters on the Foundation, the hot bag's defects fixed (step C9)
- **`Base\Service\DottedParameterBag`** (new, Foundation): parameters by their full names read
  by a dotted path; `ArrayParameterBag` and `ParameterBag` extend it. `has()` is the presence
  of a value (`false`, `0`, `""` are there; `null` is not) - it was the value's truth, for
  `ParameterBag`, `ArrayParameterBag` and `HotParameterBag` alike.
- **`Base\Service\HotParameters`** (new, Foundation): the settings stored in the database
  laid over any `ParameterBagInterface`, `HotParameterBag`'s logic with its defects fixed:
  the order is the `$bag` given, the settings, the configuration, by presence and not by
  truth (a setting stored as `false`, `0` or `""` no longer gives way to the configuration's
  value; a `null` one does); with `use_hot_bag` not `true`, the configuration is read (every
  parameter was `null`); `add()` no longer goes through the exception of the frozen bag's
  `add()`; `has()` reads the sources in `get()`'s order; the misspelt `#[SentitiveParameter]`
  gone. A path naming values under it is merged key by key from the three sources, without
  laying the whole configuration over the settings at each call.
- **`HotParameterBag`** extends `HotParameters` (no longer `ParameterBag`): the same service,
  decorating `parameter_bag` over `Base\Service\ParameterBag`, DependencyInjection's two
  interfaces, `clear()` and `remove()` refused; its constructor takes the `ParameterBag` (or a
  `Container`, as before). `HotParameterBagInterface::get()` may answer a `UnitEnum`, as
  DependencyInjection's does. `TwigSubscriber` types omnibase's interface, not the class.
- **`Base\Service\ParameterBagInterface` autowires `parameter_bag`**, the settings included -
  it gave the plain `ParameterBag`. `parameter_bag`, that autowiring, DependencyInjection's
  interfaces, `Runtime::parameters()` and `BaseTrait::getParameterBag()` are one object.
- **`SettingBag` on psr/cache** (`CacheItemPoolInterface`, `deleteItem()`), no longer
  `CacheWarmerInterface`: `Base\Bridge\Symfony\SettingBagWarmer` warms it (the same
  optional warmer). Foundation 778 -> 781 (`DottedParameterBag`, `HotParameters`, `SettingBag`).
  `HotParametersTest`, `ParameterBagTest`, `ParameterBagServicesTest`, `BareTest`.

### Fixed: two tests that depended on the machine and on the cache (step C11)
- **The forms' guard reads the clock** (`Base\Service\FormGuard::now()`), never `time()`: the
  stamp, the minimum delay, the comments' `opened` time (`CommentType`) and their flood
  interval. The clock is the framework's (Symfony's `clock`, autowired: symfony/clock's global
  clock), else the clock contract of the Foundation (`Runtime::clock()`, the machine's when
  none was given); in production the same 3 s and the same signed stamp. The HTTP tests that
  send a guarded form (`SignInByLinkHttpTest`, `RegisterAfterLoginHttpTest`,
  `ForgottenPasswordHttpTest`) move the clock past `base.guard.min_delay` between reading the
  form and sending it (`Tests\Base\Http\MovesTheClockTrait`), instead of sending it at once:
  in a site's suite (3 s) the sign-in link was refused as a robot's form on a fast machine.
  `FormGuardTest::testTheDelayIsCountedOnTheClock`.
- **A second-level cache configuration in Doctrine's order** (`usage`, then `region`):
  `IntlSubscriber` wrote the translatable's and its translations' region first, `#[Cache]` usage
  first, and the translation copied whichever its translatable held when it was loaded -
  `ClassMetadataManipulator` applies the attributes again to metadata already loaded when it
  warms the completors, so it came one way on a cold metadata cache and another on a warm one
  (`HandBuiltEntityManagerTest` failed once after `cache:clear`, on four `*Intl` classes). The
  subscriber writes Doctrine's order and puts the copy in it; the fixture of
  `HandBuiltEntityManagerTest` recorded again, its values the same (110 `cache` arrays of 85 classes reordered,
  nothing else).

### Fixed: the editor's paragraphs no longer break a line before a colon
- **editorjs-paragraph 1.0.23** (`yarn.lock`, from 1.0.22): French typography's non-breaking
  spaces. The space before `:` `;` `!` `?` `»` and after `«` becomes U+00A0 as it is typed, when
  a paragraph is drawn and in the saved HTML (`&nbsp;`), so the editor no longer puts a colon
  alone at the start of a line. An article written before is corrected once opened and saved
  again. The tool's option `nonBreakingSpaces: false` turns it off.

### What a Symfony application adds
`omnibridge/symfony` (1.x-dev) beside `omniscient/omnibase`, and the bundles its
`config/bundles.php` registers that it did not require itself - for an application coming
from 3.x, typically `api-platform/symfony`, `api-platform/doctrine-orm`,
`league/flysystem-bundle`, `nelmio/security-bundle`, `scheb/2fa` (all 25 sites already
required hwi, webauthn-symfony-bundle, nelmio/cors, Encore, ux-google, well-known and
ux-twig-component). `composer update` of those alone: every other locked package stays, and
no Flex recipe runs again.

### What a Laravel application needs
The core alone, Doctrine through `laravel-doctrine/orm` and Twig through omnibridge/twig;
omnibridge/laravel, when it exists, fills the contracts (`Base\Contract\Runtime`) with
Laravel's services. No Symfony framework package is installed, except `symfony/console`,
which doctrine/orm 3 requires and nothing of the Foundation loads.

## [Unreleased]

### Added

- **The neutral forms hold what the sites' forms need** (plan "omnibase agnostic", step F5b, group 5). `FormDefinition::TOKEN_FIELD` is
  `_csrf_token`, what omnibase's Symfony forms post. `FormDefinition::field()` (a bare field of a kind), `setGuard()`, `addRewrite()` (what was
  sent rewritten before it is read: Symfony's PRE_SUBMIT), `setValidationGroups()`, `setValidationEntity()` (the record's class-level rules on the
  form's values, as Symfony's `validation_entity`, with the fields whose refusal is said under them), `only()`, `addAction()`'s options
  (`confirmation`, `type`: ButtonType's modal). A field's fixed `data`, `choices` as a callable, a list of records as choices, `Kind::REPEATED`.
  `FormProcessor` goes through `FormGuard` (its trap and stamp in the view, what they say first; `inspectValues()`, addresses as strings where a
  host has no Symfony request) and says its refusals in the host's language. `FormSubmission::isTaken()`.
  `AccountForms`: the sign-in, the sign-in by a link, the sign-up, the profile's editor, neutral.
- **`Base\Form\Definition\UniqueLookup`**: omnibridge's `Unique` through `Repositories`, the `$exists` of the hosts' Validation.

### Security

- `#[Vault]` fails closed again: `allowsPlaintext()` called `AbstractAttribute::getParameterBag()`
  with the key, which it does not take - it gave the parameter bag itself, and `(bool)` of an
  object is true, so without a key pair every secured field (the API keys of `/admin/api-key`
  among them) was stored in clear although `base.vault.allow_plaintext` is false by default. It
  now reads the parameter and allows clear text only when it is exactly `true`. A site without
  its key pair now refuses to save a secured field (`VaultKeyNotFoundException`): make the pair
  with `secrets:generate-keys --env=<env>`. Values already stored in clear stay readable; saving
  them again seals them.

### Added
- **The account's pages are screens** (plan "omnibase agnostique", step C5, the account's part): a member's profile (`/profile/{id}`), the settings (`/settings`) and the notifications (`/notifications`) hand their template and a view model to `ScreenResponder` - `Base\Screen\Account\ProfilePage` (`Member`: number, name, avatar's address at 128 px or the application's own `avatarHtml`, roles, online, active, registered and last seen, threads and likes counted; the five latest published threads as `Link`s, now dated by `publishedAt` where a page shows it; `AccountSummary` - second factor, passkeys counted, provider, language, time zone, null where unavailable - for its owner or an administrator only; `editUrl`, `settingsUrl`, `logoutUrl`), `SettingsPage` (`TwoFactor`, `Passkey`, `Provider`, `Session`, the small forms as `Action`s - an address and the token of its intent -, `language`, `timezone`) and `NotificationsPage` (each `Notification` the bell's item and its `group` - today, yesterday, week, earlier - by the screen's clock in Twig's time zone; the token, the requests' addresses, push). Built by `Base\Screen\Builder\Accounts` (framework-free: the entities, the security policy, the Urls and Tokens contracts, the clock) through `Base\Bridge\Symfony\AccountScreens` (a Twig runtime: the viewer, `|thumbnail`, `oauth_available()`, web push, the back-office linker, Twig's time zone). The core's three templates read `screen` (and `frame.links.logout`) alone; a template a site overrides gets the variables of before as well (`ScreenResponder::legacy()`, deprecated, gone in 4.0); a host page rendering `@Base/client/user/profile_show.html.twig` with the user alone gets the model from `account_profile(user)`. The settings and the notifications are `Confidential`: `private, no-store`, `Vary: Cookie`. The forms (profile edit, sign-in, sign-up, passwords, two-factor setup), the enrolment prompt and the security flow stay Symfony's (D3). Foundation 744 -> 758. Tests: `AccountPagesSnapshotTest` (the ten pages byte for byte as recorded before, and their props: a visitor's carry what the visitor's page shows, the owner's the summary, each action the token of its intent, the groups by a fixed clock, no cache for tokens), `BareTest::testTheAccountScreensAreBuiltWithoutTheFramework`, `ScreenModelsTest`.
- **The Tokens contract, `Base\Contract\Tokens`** (plan "omnibase agnostique", step C5; decision D4: `csrf_token` enters the view contract): `csrf($intent)`, the token a form carries for an intent, what a screen's actions send back and their controllers check; `Runtime::tokens()`, in the `base.contracts` locator. Under Symfony `Base\Bridge\Symfony\CsrfTokens` (the CSRF token manager: Twig's `csrf_token()`). docs/20-architecture/contracts.md says that Laravel has one session token for every intent: an action pointing at a Symfony route, rendered by Laravel, would carry a token Symfony refuses (nothing does that today). Foundation 741 -> 743. Test: `ScreenAdaptersTest::testTokensAreTheFormsTokens`.
- **The screens: a page as a template and a view model** (plan "omnibase agnostique", step C5, the core's side; rule 13). `Base\Screen\*`, framework-free `readonly` view models whose `toArray()` are a front end's props and whose public properties Twig reads: `Frame` (locale, `Site`, links, `Viewer`, theme, a site's own fields in `extra` - refused when they would override the frame's own, `RESERVED` -, and the host, not a prop), `Author`, `Tag`, `Link`, `StoryText`, `Card`, `Story`, `Comment`, `Reply`, `Discussion`; the names and order of lesringostars' `App\Screen\{Frame, BlogPage, StoryPage, StoryText}`, the additions agreed with the pilot after them (a card's `likes`, `likeUrl`, `comments` null where nothing is counted, never 0; `recent` computed by the builder's clock, so no template compares a moment; a comment and a reply named by an id or a uuid, at least one; the comments of a page in a `Discussion`, which the visitors' book and a host's page show too). `Base\Screen\View` (template, screen, React component, status), `Base\Screen\ContextProvider` (the frame, the viewer, the flashes of a request). `Base\Screen\Builder\Stories` makes cards, stories, links, tags and comments of a `Thread` and its `Base\Entity\Thread\Comment`s with two new contracts, `Base\Contract\Pictures` (`|picture`) and `Base\Contract\Texts` (`|wysiwyg`, `|wysiwyg_toc`), and `Runtime::clock()`. Under Symfony: `Base\Bridge\Symfony\MediaPictures` and `WysiwygTexts` (the filters' own answers, in the `base.contracts` locator, `Runtime::pictures()`, `Runtime::texts()`), `SymfonyScreenContext` (the request's locale and host, the host's `site` Twig global or omnibase's settings, the links whose routes exist, the Access contract's user, the Themizer's scheme, `app.flashes()`; service `Base\Screen\ContextProvider`, a site decorates it), `ScreenResponder` (Twig with `frame` and `screen`, plus a site's variables of before; omnibridge/react's protocol when it is installed - `X-Omnibridge`, the View's component, the frame's and the screen's props, `base.screens.version` (new, null), 409 for another version -, else a request asking `application/json` alone gets the props; `Vary` on what chose; a JSON answer carrying a viewer `private` and varying on `Cookie`). Foundation 718 -> 741; the names report: 136 of 741 name the framework themselves (`SymfonyScreenContext`, `ScreenResponder` join), 427 with the chains. Tests: `tests/Screen/ScreenModelsTest`, `tests/Bridge/Symfony/ScreenResponderTest` (the protocol's answers when omnibridge/react is beside the core or mounted by the harness's checkouts overlay at `/srv/omnibridge/react`), `ScreenAdaptersTest` (the context, the flashes taken once, Pictures and Texts equal to `|picture` and `|wysiwyg`), `BareTest::testTheScreensAreBuiltWithoutTheFramework`. Docs: `docs/20-architecture/contracts.md` (The screens).
- **omnibase's Doctrine mapping on an entity manager built by hand, without DoctrineBundle** (plan "omnibase agnostique", step C6): `Base\Bridge\Doctrine\Mapping::register($entityManager, $options)`, on omnibridge/doctrine's `EntityManagers::create($connection, $paths)` (or any entity manager), before its first metadata is read - the class convention (`ClassAliases`), the column types (`Types`), omnibase's naming strategy, the filters and the DQL functions (`Configuration`), the driver wrapped (`MappingDriver`), the ClassMetadataManipulator and the AttributeReader without the framework's services, the listeners in DoctrineBundle's order (`AttributeSubscriber`, `IntlSubscriber`, the targets resolved and the entity listeners attached when given, `TrackingPolicySubscriber`, `ExtensionSubscriber`; on flush `TrasheableSubscriber`, `VersionableSubscriber`, `InverseCollectionCacheSubscriber`), the trash and vault filters enabled. `Mapping::getRepository()`: the repositories built from `Base\Bridge\Doctrine\Registry` (a ManagerRegistry over one entity manager) by `Base\Bridge\Doctrine\RepositoryFactory`. Doctrine's own ClassMetadataFactory is used. `tests/Bridge/Doctrine/HandBuiltEntityManagerTest::testTheHandBuiltEntityManagerGivesSymfonysMetadata`: built in a PHP process of its own (`hand-built.php`: the autoloader, a DBAL connection, `EntityManagers::create()`, `Mapping::register()`), it gives the metadata Symfony's gives for each of the 201 classes of the harness, the same filters, Symfony's types (but doctrine-bridge's own), and reads the thread Symfony wrote, by DQL and by its repository; the process loads neither the kernel nor a bundle. See docs/20-architecture/doctrine.md.
- **`Base\Bridge\Doctrine\MetadataSnapshot`, an entity manager's mapping as data** (plan "omnibase agnostique", step C6, the measure): for each class its ClassMetadata - every public property (table, inheritance, discriminator map and value, fields and their types, associations, cache, callbacks, entity listeners, repository...), the reflection left out, the id generator by its class, a mapping object's null properties left out, the discriminator map and the subclasses sorted (a generated map lists the classes in the order PHP declared them, which two processes need not share) -, what each enabled SQL filter adds to a query of it, the completor omnibase's attributes filled for it (aliases, hierarchy); then the filters enabled and the DBAL types registered. `tests/Bridge/Doctrine/HandBuiltEntityManagerTest::testSymfonysMetadataIsThatOfBefore` compares Symfony's, class by class, with `fixtures/doctrine-metadata.json`, recorded on the code of before C6 (`DOCTRINE_SNAPSHOT=1`): 201 classes in the harness.
- **The harness runs omnibase/blog's and omnibase/classroom's suites** (`test blog`, `test classroom`). The image does not install them; `compose.checkouts.yml` mounts their checkouts beside the others, and the demo's `config/bundles.php` and `config/routes/plugins.php` (new), mounted as checked out, register their bundles and their pages when their own suite runs - their tests' bootstrap (`HARNESS_SUITE_BOOTSTRAP`), which registers their classes, is read by `bundles.php` too, so that the schema made before the suite has their tables; the other suites map the same entities as before. `demo.sh` (mounted as checked out as well) removes the test container before linking the bundles' public files: `assets:install` booted the previous suite's container, which could list a bundle the next one has not ("Class Base\Blog\BlogBundle not found"). blog 29 tests (1 skipped), classroom 27 (1 skipped).
- **Links through the Urls contract, and `Base\Contract\Link`** (plan "omnibase agnostique", step C3e, the first group: the links). `Link::to($name, $parameters, $referenceType)` makes a route's address with a router's reference type through `Runtime::urls()`: `Link::ABSOLUTE_PATH` is `path()`, `Link::ABSOLUTE_URL` is `url()` (the values of Symfony's `UrlGeneratorInterface` constants, named so that a link is made without the routing component); a relative or a network path is asked of the implementation's own `generate()` - `Base\Bridge\Symfony\RouterUrls::generate()`, new, the router - and refused by an implementation without one. `Base\Entity\Layout\Semantic::__toLink()` and `getUrl()` (its `generate()`) and `ShortLink::__toLink()` make their addresses with it instead of `BaseTrait`'s router: the router's strings under Symfony for the four reference types, and an address where there is no framework (ShortLink reached the router through its current translation, so through the Localizer, first); Semantic still answers null for a route the router cannot make, and says aloud a registry without Urls (`MissingContractException`), never taken for a missing route. `Base\Service\Model\LinkableInterface::__toLink()`'s default is `Link::ABSOLUTE_PATH` (the same value; the interface names nothing of the routing component). `Base\Contract\Urls` no longer declares `url()`: omnibridge/twig's `Urls` has it since d786505, and omnibase's only extends it (same name, same method, `RouterUrls` unchanged). Foundation 691 -> 692 (`Link`); names report: 133 -> 130 classes naming the framework themselves (Semantic, ShortLink, LinkableInterface), 420 -> 418 with the chains. Tests: `tests/Contract/EntityGettersTest` (the four reference types of Semantic, ShortLink and `Link::to()` equal to `advanced_router`'s and to `BaseTrait`'s router, a missing route null, no Urls said aloud), `BareTest::testTheEntitiesAnswerThroughTheContractsWithoutTheFramework` (`tests/Contract/entities-without-framework.php`: the real Semantic and ShortLink, plain Urls set by hand, nothing of the framework loaded).
- **The Symfony adapters of the contracts, wired by the bundle** (plan "omnibase agnostique", step C4): `Base\Bridge\Symfony\RouterUrls` (`advanced_router` and the asset packages), `TranslatorTranslations` (the `translator` chain: the texts rewritten in the back office, `Base\Service\Translator`, Symfony's), `SecurityAccess` (the authorization checker and the token storage), `LocalizerLocales` (the `localizer`) and `FlysystemFiles` (`flysystem`, and the image route for a remote storage, as the uploader resolves it), each bound to its contract's name in `config/services/core.php` (an application rebinds one by aliasing the interface). `Base\DependencyInjection\Compiler\Pass\ContractsPass` builds the `base.contracts` locator - the five, the framework's `clock`, `event_dispatcher` and `mailer.mailer`, only those whose service exists - and `BaseBundle::boot()` hands it to `Base\Contract\Runtime` after the static locator; a locator holds closures, nothing is built before a contract is asked. No existing service changes: same ids, classes and arguments. The adapters implement only the contracts and load nothing of the framework (they name it): `tests/Foundation.php` 686 -> 691; the names report 133 of 691 (the two more are `RouterUrls` and `SecurityAccess`), 420 with the `Base\` names. Test: `tests/Bridge/Symfony/ContractAdaptersTest` (the registry is the bundle's locator, refilled by a new kernel; `path()`, `url()`, `asset()` equal to the router's, `AdvancedRouter::getAssetUrl()` and Twig's; `trans()` equal to the translator's, through `BaseTrait` and Twig's `|trans`, ICU placeholders, plurals and `@domain.key` references; `isGranted()` equal to the authorization checker's, `BaseService::isGranted()` and Twig's `is_granted()`, nobody signed in then an administrator, with and without a `Thread`; the locales the `localizer`'s; a stored file's paths `Flysystem`'s). Docs: `docs/20-architecture/contracts.md`.
- **The contracts of the Foundation and their registry, `Base\Contract\Runtime`** (plan "omnibase agnostique", step C4). What omnibase's framework-free code asks of the application it runs in, by an interface: `Base\Contract\Urls` (omnibridge/twig's `Urls` - `path()`, `asset()` - and `url()`, the address whole, which omnibridge's lacks), omnibridge/twig's `Translations` (`trans()`, ICU) reused as it is, `Base\Contract\Access` (`isGranted()` an attribute on a subject, `getUser()`), `Base\Contract\Locales` (the current, default and available locales, `fr-FR`; plural like omnibridge's, and not to shadow PHP's `\Locale`), `Base\Contract\Files` (a stored file's public path and web address, its local path, whether its storage is remote: the Flysystem-free part the uploader and the entities need), PSR-20's `ClockInterface` and PSR-14's `EventDispatcherInterface` rather than contracts of the core's, and symfony/mailer's `MailerInterface`: no contract for the mail, the component is allowed in the Foundation (D1) and Laravel's mail is built on it. `Runtime` reaches each by its interface's name, from a PSR-11 container (`setContainer()`) or set one at a time (`set()`, ahead of the container's); a contract asked and not filled throws `Base\Exception\MissingContractException`, which names it, where a getter through `BaseTrait` returned null in silence (`find()` answers null for a caller that can do without); the clock is the machine's (`Base\Contract\SystemClock`) when none was given; a different container forgets what the previous one gave. psr/clock, psr/container and psr/event-dispatcher are required (each was installed already, through Symfony). The entities' getters do not use them yet (step C3e). The seven classes load without the framework and enter `tests/Foundation.php` (679 -> 686); none names it. `docker/bin/bare` runs a script given in place of a class, with the autoloader alone, and reports what it returns. Tests: `tests/Contract/RuntimeTest` (the registry alone), `tests/BareTest` (`tests/Contract/runtime-without-framework.php`: plain PHP implementations bound by the contracts' names as another framework binds them, a user of the contracts written as C3e writes the getters - its link and address, what the visitor may do, an ICU label in two languages, its age by the clock, its thumbnail, an event - answers through them and nothing of the framework is loaded).
- **The harness's `bare --names`, the informative report of the names** (plan "omnibase agnostique", §6, the decision on C1). Nothing is loaded: each file of `src/` is read - its `use` lines (grouped ones expanded, `use function`, `use const`) and its fully qualified names - and the report says which classes of the Foundation (or of `--all`, or of the classes given) still name the framework themselves, and which name it through the `Base\` classes they name, followed to the end (the chain printed). It informs, it does not guard (exit 0): the Foundation, measured by loading, only grows; this report only falls. On 2026-10-18, of the 679 classes of the Foundation, 131 name the framework themselves, 417 themselves or through a `Base\` name. The harness's image is rebuilt with `OMNI_REFRESH`: it has omnibridge/doctrine cf6b3b9, and the suite no longer needs a checkout mounted over `vendor/omnibridge/doctrine`. Docs: `docs/30-operations/harness.md` (The names report, Rebuilding the image).
- **The package is `omniscient/omnibase`** (formerly `glitchr/omnibase`, kept as a `replace`: a `composer.json` still requiring the old name resolves to this package and never installs both; the omni families' cores moved to the vendor `omniscient/` the same way). Composer installs it in `vendor/omniscient/omnibase`, and nothing in the package depends on where it lives: `base.twig.default_path` defaults to the package's own `templates/` (it named `%kernel.project_dir%/vendor/glitchr/omnibase/templates`), and a configured path that is not there (a site still naming the old one) is the package's templates too; the container parameter `base.package_dir` is the package's directory, for an application's configuration to name instead of a vendor path (`'%base.package_dir%/docs'` for wikidoc's roots); `deploy_version()` folds the git HEADs of `vendor/{glitchr,omniscient,omnibase}/*` (it read `vendor/glitchr/*` alone, where this package no longer is). The harness installs and mounts it under its new name (`docker/Dockerfile`, `compose.checkouts.yml`, `demo.sh`: `bare`, `test`; image `omniscient/omnibase-harness`). Namespace `Base\` and the GitHub repository unchanged. What an application still names by the old path - `config/packages/base.yaml`'s `twig.default_path`, `wikidoc.yaml`, `git.yaml`, `composer.json`'s autoload entries, a Makefile's `build-vendor glitchr/omnibase`, the `.env` Flex markers, Docker mounts - it changes on its own (plan "omnibase agnostique", the site sweep). PHP 8.2 at least (omnibridge/doctrine's).
- **omnibridge/doctrine and omnibridge/twig are required** (plan "omnibase agnostique", step C2): what the pilot copied out of the core comes back from there, names kept. `Base\Database\Mapping\NamingStrategy` extends `Omnibridge\Doctrine\NamingStrategy` and keeps what it adds (a class given as an object, every attribute of an entity checked to serialize); `DateTimeTypeUTC` extends its `DateTimeType`; `UtcDateTimeType` and `UtcDateTimeImmutableType` extend its `UtcDateTimeType` and `UtcDateTimeImmutableType` (they delegated to them while those were final; omnibridge/doctrine cf6b3b9 or later is needed, as `1.x-dev` resolves today), the same conversions and names; `Utc::zone()` is its zone. `BaseBundle::boot()` keeps registering omnibase's classes rather than calling `Omnibridge\Doctrine\Types::register()`, which would put omnibridge's classes under the same names and ignore `base.database.use_custom`; `tests/Database/Type/OmnibridgeTypesTest` proves both convert alike. `Omnibridge\Twig\HelperExtension` is registered (priority 255, before every other extension): `html_attributes`, `mb_ucfirst`, `second`, `join_if_exists` and `less_than` are its, gone from `FunctionTwigExtension` (the same results; `html_decode` is new to the core's sites); omnibase's own `str_shorten`, `highlight`, `datetime`, `lang`, `country` and `lang_name` do more and are registered after it, so they win. An application whose root `composer.json` takes stable packages (`minimum-stability` stable - lesringostars' sits under `config`, where Composer does not read it) requires `omnibridge/doctrine` and `omnibridge/twig` `1.x-dev` itself until they are tagged: Composer takes a dependency's dev version only from the root. Tests: `tests/Database/Mapping/NamingStrategyTest` (omnibridge/doctrine's lesringostars tables, every table and join table of the site), `tests/Twig/HelperExtensionTest` (each shared helper rendered by the application and by a bare Twig with omnibridge/twig alone, the same text; `datetime` lowered alike).
- **`Resources/Functions.php` declares a function only when its name is free** (`if (!function_exists(...))`, all 296): beside Laravel, whose `head()`, `last()` and `class_basename()` share omnibase's names, the file no longer dies on a redeclaration. Where Laravel's differ, omnibase's own code calls names of its own with omnibase's results: `begin()` for `head()` (null for an empty list, Laravel's false), `array_end()` for `last()` (by reference, the pointer moved, as before) and `class_name()` for `class_basename()` (a list, `Foo::bar`, nothing), the Twig filter `head` too; `last()` now takes its array by value (the same value, a function's result accepted, as Laravel's). Test: `tests/Resources/FunctionsGuardTest` (Laravel's helpers first, then the file: Laravel's results for Laravel's code, omnibase's for omnibase's; the other order; every function guarded).
- **The Foundation and the test without the framework** (plan "omnibase agnostique", step C1). `tests/Foundation.php` lists the classes of the core that load without the framework, sorted: 599 of the 895 classes of `src/` today. `tests/BareTest.php` runs `docker/bin/bare` in a PHP process of its own, with Composer's autoloader alone (no kernel, no container, no bundle booted), loads each of them and fails if a class or a file of the framework is loaded on the way - the Symfony bundles and bridges, `dependency-injection`, `config`, `http-kernel`, `http-foundation`, `routing`, `security-*`, `form`, `console`, `ux-*`, API Platform and the third-party bundles; the standalone Symfony components, Doctrine and Twig are allowed - naming the framework's class or file, the class of the list that loaded it and the one that asked for it. A second case loads `Base\BaseBundle` and `Base\Repository\UserRepository` and checks the report names the framework. The list only grows: a class leaves it only by a decision written down in the plan and here. The harness has a `bare` command (`demo.sh`: the Foundation, the classes named, `--all --each` for every class of `src/`, `--json`). Docs: `docs/30-operations/harness.md` (The Foundation: `bare`).
- **A route can keep omnibase from adding to its page**: the default `_omnibase_inject: false` (`Base\Subscriber\TwigSubscriber::INJECT`) leaves the response as it was rendered, without the breakpoints' styles, the HTML queued while rendering, nor the webpack entries not printed. For a page rendered whole, its own markup and nothing else: lesringostars' screens, which Laravel renders too (plan 0, rule 13), came out 60 KB longer under Symfony. Any other page gets the additions as before. Test: `tests/Subscriber/TwigSubscriberInjectTest`.

### Changed
- **`ScreenResponder` refuses a screen taking the frame's keys, and keeps a confidential screen out of every cache** (plan "omnibase agnostique", step C5, review of the account's part). A screen's props merge under the frame's (`Frame::RESERVED`: locale, site, links, viewer, theme), the frame's first: a screen giving one of them lost its own without a word (omnibase/blog's `links`, now `indexUrl`/`feedUrl`, 4dd1ec2). `ScreenResponder::guard()` throws for such a screen, in every answer, HTML or JSON. `Base\Screen\Confidential` (new, a marker extending `Screen`) is a screen carrying its viewer's secrets (its forms' tokens): its answers, HTML and JSON, a visitor or none, are `Cache-Control: private, no-store` with `Vary: Cookie`. Tests: `ScreenResponderTest::testAScreenMayNotTakeTheFramesKeys`, `testAConfidentialScreenIsStoredByNoCache`.
- **`|datetime` reads a moment in ISO 8601** (plan "omnibase agnostique", step C5, the account's part): a string such as `2026-04-10T18:05:00+00:00` - what a screen carries, `Base\Screen\Shape::moment()` - is that moment, printed as the `DateTime` it came from was (the same timestamp through the same formatter, whatever zone either was written in); any other string is still given back lowered, nothing still gives the pattern. `FunctionTwigExtension::isMoment()` says which strings are moments. Test: `HelperExtensionTest::testAMomentInIso8601IsThatMoment`.
- **The hand-built entity manager's repositories come from its own `getRepository()`** (plan "omnibase agnostique", C6's follow-up): `Base\Bridge\Doctrine\Mapping::configure()`, given to omnibridge/doctrine's `EntityManagers::create(..., configure:)` (4d2436d: the configuration handed over before `new EntityManager`, and an in-memory metadata cache by default), sets `Base\Bridge\Doctrine\RepositoryFactory`, which now builds its registry over the entity manager asking when none is given (`registryFor()`); `Mapping::register()` takes that factory and its registry. `Mapping::getRepository()` is deprecated, the same repository, kept for an entity manager built without `configure:`. The harness mounts omnibridge/doctrine's checkout (`compose.checkouts.yml`) until its image is rebuilt. Test: `HandBuiltEntityManagerTest` (the repository factory set before the entity manager, the repository the same through both).
- **The hand-built entity manager in an application** (plan "omnibase agnostique", step C6, on lesringostars): `MappingDriver::getAllClassNames()` leaves out the aliases (an attribute driver over every folder listed `app\entity\thread\tag`, the lower-cased `class_alias()` of `Base\Entity\Thread\Tag`; DoctrineBundle's chain dropped it by its namespaces' case); `MetadataSnapshot` leaves out a mapping object's false flags too and sorts the property accessors (read back from the metadata cache, a field's `nullable`, `unique`, `index` are null where a mapping just built says false); `hand-built.php` gives `EntityManagers::create()` an in-memory metadata cache (ORMSetup's default asks a Redis server on 127.0.0.1 where APCu is missing); `HandBuiltEntityManagerTest` compares the classes the attribute drivers map, leaving out those a bundle maps with a driver of its own (WebauthnBundle's XML credentials) and the fields an entity inherits from them (`Passkey`).
- **The Doctrine mapping runs on doctrine/orm as published** (plan "omnibase agnostique", step C6, the move). `Base\Database\Mapping\ClassMetadataFactory` (still the applications' `class_metadata_factory_name`) uses nothing Doctrine keeps private: it keeps the entity manager it is given (`setEntityManager()`), the naming strategy given back to a ClassMetadata read from the cache, `getAllClassNames()` and `isEntity()`; its `getShortName()` override, its `doLoadMetadata()` and `initializeReflection()` are gone. What they did is `Base\Bridge\Doctrine\MappingDriver`'s, the driver `DoctrineConfigurationPass` wraps every entity manager's in: before a class is read, the table name checked (two entity classes may not share one) and the `resolveDiscriminator` event (`IntlSubscriber`: a translation class takes its translatable's inheritance and map), then the discriminator value looked up; after, **a root entity that declares an inheritance and no map gets one generated** - the class and its subclasses, each under its class name, the keys the factory's `getShortName()` gave, which `#[DiscriminatorEntry]` renames as before - so Doctrine never generates its own under the short names. The column types are `Base\Bridge\Doctrine\Types::register()`'s (`BaseBundle::boot()` calls it: `datetime`/`datetimetz` the core's `DateTimeTypeUTC`, `array`, the UTC types, the enumerations of the core and of the application), the filters, the DQL functions and the default hint `Base\Bridge\Doctrine\Configuration`'s lists (the pass hands them to DoctrineBundle; `enableFilters()` at boot). `composer.json` says `extra.base-plugin.doctrine/orm: false`: glitchr/base-plugin (2.0, from bc3ef78) leaves doctrine/orm as published for this core. The metadata of the harness's 201 classes are those of before.
- **What the Doctrine mapping reads through loads nothing of the framework** (plan "omnibase agnostique", step C6, the seams). `Base\Cache\Abstract\AbstractLocalCacheInterface` declares `warmUp()` itself, with HttpKernel's signature, and no longer extends `WarmableInterface` (omnibase's warmers call it; Symfony asks `WarmableInterface` of the kernel, its router and its translator only): the AttributeReader, the ClassMetadataManipulator, the Localizer, the IconProvider and its adapters, the Themizer, the Obfuscator, `SimpleCache` and `WebpackTagRenderer` load without it. The AttributeReader's event dispatcher, router, filesystem, request stack and token storage may be null (an asset's base path, the user signed in, the routes' attributes answer without them), and it lists the attributes of a folder with `Base\Service\Model\ClassFinder` (new: what `AbstractBaseBundle::getAllClasses()` answers, without loading the bundle). `IntlSubscriber` and `TranslatableWalker` take no localizer as well. `ClassMetadataManipulator::getAllClassNames()` asks the mapping driver when the factory is Doctrine's own. `Base\Service\ArrayParameterBag` (new): omnibase's `ParameterBagInterface` over an array of parameters by their full names. `Base\Service\Model\ClassAliases` (new): the `App\Entity\X` = `Base\Entity\X` convention (and `App\Repository`, `App\Enum`, `App\Tests`, `App\Notifier`, `App\Form`) as an autoloader of its own; `AbstractBaseBundle`'s fallback autoloader asks it.
- **`Base\Entity\User\Notification` cut in two** (plan "omnibase agnostique", step C3f). The entity keeps what a notification is and its sending through the notifier; what needed Symfony is `Base\Bridge\Symfony\NotificationMessages`', moved as it was: `email()` (`asEmailMessage()`: Twig's `NotificationEmail` - `TemplatedEmail`, `WrappedTemplatedEmail` -, the `UploadedFile` attachments embedded, the HTML rendered, the test and forwarding prefixes), `response()` (`render()`'s HttpFoundation `Response`, of the new `renderHtml()`), `throwable()` (an `ExceptionEvent`'s error or a `FlattenException` made the content). Same class names and methods for the sites; the entity reads its own state through its getters (`addHtmlParameter()` for the test footer). `addAttachment()` / `removeAttachment()` take a `\SplFileInfo` (an `UploadedFile` still, the e-mail embedding only those, as before); `render()` and `BaseNotificationInterface::render()` are untyped (`@return Response`; an implementation typed `: Response` stays compatible). The entity and the interface name nothing of the framework.
- **The translatable entities' languages come from the Locales contract** (plan "omnibase agnostique", step C3e, the third group: the titles through the Localizer). `TranslatableTrait::translate()` - every translatable entity's translation for the page, so its titles and every getter and setter `__call` sends to the translation -, its `__get` and `__isset` fallbacks to the default locale and `addTranslation()`, and `TranslationTrait::getLocale()` / `setLocale()`, asked `BaseService::getLocalizer()` or the Localizer's statics; they ask `Runtime::find(Locales::class)` (none in the registry: `translate()` answers null, as when no Localizer was found) and the new `Base\Service\Model\LocaleNormalizer`, the Localizer's rule for omnibase's `xx-XX` form taken out of it (`normalize()`, `lang()`, intl's languages and countries in `getLocales()`, and `here()` with the contract's default and available locales). `Localizer::normalizeLocale()`, `__toLocaleLang()` and `getLocales()` ask it with their own statics: the same strings by construction. Under Symfony the Locales contract is the Localizer's (`LocalizerLocales`), so the same translation is picked and the same titles read; where there is no framework, a translatable entity is written and read in the page's language, the same language elsewhere, the default. `TranslatableTrait` and `TranslationTrait` no longer name `BaseService` nor the Localizer. The harness's `bare` no longer takes symfony/intl's resource readers (`Symfony\Component\Intl\Data\Bundle\...`) for a bundle's classes.
- **`Thread::isVisible()` and `User::isGranted()` ask the Access contract** (plan "omnibase agnostique", step C3e, the second group: access). Each asked `BaseService::isGranted()` through `BaseTrait`; they ask `Runtime::access()`, under Symfony the same authorization checker (`SecurityAccess`). `isVisible()` also asked whether the thread's owners contained the token storage's token - a token compared with users, never one of them, so never true: the line is dropped with the token storage, and the answers are those of before (an administrator sees every thread, anyone else an approved, published, not secret one). Whether an owner should see their own unpublished thread is left as a decision for later (plan, C3e). Thread answers where there is no framework; User still loads it (Security's `UserInterface`, Scheb's interfaces).
- **The uploader takes any `\SplFileInfo`** (plan "omnibase agnostique", step C3d): `Base\Database\Attribute\Uploader` no longer imports HttpFoundation's `File` and `UploadedFile`. What it takes for a field - checked by `instanceof \SplFileInfo` where it checked `instanceof File` - is Symfony's `File` or `UploadedFile` as before, or a plain `\SplFileInfo` (an application without HttpFoundation's request files); what it gives back (`get()`, `materializeTemporaryFile()`, now typed `?\SplFileInfo`) is made by `Uploader::file()`, **Symfony's `File` wherever HttpFoundation is installed** - every Symfony application, so an entity's getter typed `?File` and the forms' `FileType` get the class they had - and a plain `\SplFileInfo` elsewhere; the class is named by a string (`Uploader::SYMFONY_FILE`). The size limit is `Uploader::getUploadMaxFilesize()` (PHP's `post_max_size` and `upload_max_filesize`, parsed as `UploadedFile::getMaxFilesize()` parses them) and the MIME type `Uploader::getFileMimeType()` (the file's own `getMimeType()`, symfony/mime's guess for a plain `\SplFileInfo`). Same uploads, same names stored, same checks. Uploader names no class of the framework any more (the names report: 132 -> 131); it loaded without it already. Test: `tests/Database/Attribute/UploaderSplFileInfoTest` (a plain `\SplFileInfo` set on a widget's thumbnail is stored under its name and comes back as Symfony's `File`, the same bytes; the limit and the MIME type are Symfony's).
- **`Base\Service\ParameterBagInterface` is the core's own contract** (plan "omnibase agnostique", step C3c): it no longer extends DependencyInjection's `ParameterBagInterface` and declares what omnibase reads - `get()` by a dotted path, `set()`, `has()`, and now `all()`. `Base\Service\ParameterBag` (and `HotParameterBag`) is its Symfony adapter, unchanged: it extends `ContainerBag`, decorates `parameter_bag`, and is DependencyInjection's bag as well for the code that types that one (`Localizer`, `Paginator`, `MediaService`, `TimeMachine`, the Twig loader, marketplace's `Shipping`), so every service gets the same object as before. `AbstractAttribute::getParameterBag()` and `AttributeReader` (its property, constructor and getter) are typed on the core's contract instead of DependencyInjection's: `AbstractAttribute` names no class of the framework any more, and the contract enters `tests/Foundation.php` (679). **Code that typed a parameter as `Base\Service\ParameterBagInterface` and calls a method of DependencyInjection's bag on it** (`resolveValue()`, `add()`...) still works on the container's bag, not on a mock of the interface; none in the core, the bundles nor lesringostars does. Test: `tests/Attributes/AttributeParameterBagTest`.
- **`Base\Database\Repository\ServiceEntityRepository` is doctrine/orm's `EntityRepository`**, no longer DoctrineBundle's `ServiceEntityRepository` (plan "omnibase agnostique", step C3b). Its constructor is the same, `__construct(ManagerRegistry $doctrine, ?string $entityName = null)` (the `ManagerRegistry` contract of doctrine/persistence, the entity guessed from the repository's name), and resolves the entity manager of the class as DoctrineBundle's did (`getManagerForClass()`, the same `LogicException` when there is none), then builds the `EntityRepository` from it; the finder language, `find()`, `findBy()`, `count()`, `persist()` and the rest are unchanged. Loading a repository no longer loads the framework: **79 classes enter `tests/Foundation.php` (599 → 678)** - every repository of the core but the users' (Security's user provider) and the passkeys' (Webauthn's bundle). Under Symfony nothing changes for a caller: `BaseExtension` tags every service extending the class `doctrine.repository_service` (`registerForAutoconfiguration()`, where DoctrineBundle tagged its interface), so an application's `App\Repository\*` is still handed out by `getRepository()` and autowired; `AbstractBaseBundle::isRepositoryService()` takes a subclass of omnibase's class (or DoctrineBundle's interface, for the bundles' repositories still on DoctrineBundle's class); `AbstractAttribute::getPropertyOwnerRepository()` returns an `EntityRepository` (it promised DoctrineBundle's `ServiceEntityRepositoryInterface`, which omnibase's repositories no longer implement). **An application's repository extending omnibase's** must be a service of a container that autoconfigures it (Symfony's default `services.yaml` does), as it had to be before. Tests: `tests/Database/Repository/RepositoryServiceTest` (the core's and the application's repository handed out by `getRepository()`, the container's, an `EntityRepository` without DoctrineBundle's interface), `tests/BareTest` (a repository loads nothing of the framework; the case that proves the check is not blind now reads Security's interface on the users' repository). Docs: `docs/20-architecture/entities-and-repositories.md`, `docs/30-operations/harness.md`.
- **The nouns of the translator are `TranslatorInterface`'s** (plan "omnibase agnostique", step C3a): `TRANSLATION_NOUN`, `NOUN_SINGULAR` and `NOUN_PLURAL` are declared on `Base\Service\TranslatorInterface`, and `Translator::NOUN_SINGULAR` (and the others) still name them, by inheritance. `Base\Database\Type\EnumType::getText()` and `Service\Model\Autocomplete` ask for the singular noun by the interface: the type of the 20 enumerations of the core no longer names `Translator`, which imports the framework's kernel. They loaded without the framework already (the constant is read when `getText()` runs); they now no longer name it either (the informative names report of the Foundation, through the `Base\` classes a class names: 355 classes, now 339). Same values, same texts. Test: `tests/Database/Type/EnumTypeNounTest`.
- **`AdvancedRouter::getScheme()` is the scheme of the request being handled.** It read `$_SERVER["REQUEST_SCHEME"] ?? true` as a boolean, and "http" is a true string: https whatever the request. It is now `get_url_scheme()` of the main request (`Request::isSecure()`, so a trusted proxy's `X-Forwarded-Proto`), the scheme `get_url()` gives the site's routes, the same on PHP's globals for a web request not yet on the stack, and https without a request (a command, a worker) unless the site sets `HTTPS` or `USE_HTTPS` to a false value. On a site served over https nothing changes. Over plain http (the harness, the sites' tests, latoucheoriginale on 8081): an address of `_profiler`, `_wdt` or `_switch_locale` is http, the host fallbacks' ports are filtered by `HTTP_PORT` as `getPort()` already judged them (latoucheoriginale's fallback port is 8081, not 443), and `AdvancedUrlGenerator::generate()` no longer writes every link whole because `HTTPS` is on while the request is http (an address with the request's own scheme and host, which a path says as well; a route with a host of its own still is). `docs/decisions/router-scheme.md` says what it answers, what moved and how it is checked. Test: `tests/Routing/RouterSchemeTest`.
- **The web debug toolbar is `glitchr/symfony-profiler-pack`'s alone.** This bundle's copy of it is gone (`templates/bundles/WebProfilerBundle/Profiler/toolbar_js.html.twig`, `toolbar.css.twig`, `profiler.css.twig`: the floating toolbar, mouse only, and the profiler's wider pages, all in the pack), and with it the loader's ordering that put the pack ahead of it. Every site takes the pack as a development requirement (`composer require --dev glitchr/symfony-profiler-pack`): **a site without it has Symfony's own toolbar**. This bundle's other WebProfiler templates stay (the bundles' and localizer's collectors and icons, the mailer panel, the exception styles).
- **`@Base` is declared by the core's Twig loader** (`Base\Twig\Loader\FilesystemLoader`): an application's override (`templates/bundles/BaseBundle/`) first, then the bundle's templates (`base.twig.default_path`); `@!Base` the templates alone. It came from Symfony's bundle path only, `src/Resources/views`, a link that a copy of the package loses (Composer's path repository with `symlink: false`, as the harness installs it): there every page including one of the core's partials failed ("There are no registered paths for namespace Base") and the harness ran out of memory at 81%. Where the link is there (every site), nothing is added twice. The declaration is Twig's, not Symfony's: it is the loader's, the piece meant for `omnibridge/twig`. Test: `tests/Twig/BaseNamespaceTest`. The harness now runs to its end (775 tests); its remaining failures come from the other links the copy loses (`src/Resources/translations`, `src/Resources/public`).
- **The web debug toolbar is `glitchr/symfony-profiler-pack`'s where a site installs it.** This bundle's copy of the toolbar (`templates/bundles/WebProfilerBundle/Profiler/toolbar_js.html.twig`, `toolbar.css.twig`: the floating toolbar, mouse only) came first under `@WebProfiler` on every site, ahead of the pack's: a site with the pack still had the old one. `Base\Twig\Loader\FilesystemLoader` now puts this bundle's `WebProfilerBundle` override right after the pack's when the pack is there, so the toolbar is the pack's (moved by a finger as by the mouse, a handle the size of a fingertip on a touch screen, a double tap to send it aside) and this bundle's other WebProfiler templates (the bundles' and localizer's collectors, the mailer panel) are still found. Without the pack nothing changes. Test: `tests/Twig/ProfilerPackOrderTest`. **A site takes the pack as a development requirement** (`composer require --dev glitchr/symfony-profiler-pack`, a VCS entry for `github.com/glitchr-studio/symfony-profiler-pack` until it is on Packagist); this bundle's copy of the toolbar is to go once every site has.
- **The demonstration names its account on every page, and its back office is no longer public** (`docs/20-architecture/demo.md`). The banner (`@Base/demo/_banner.html.twig`) tells a visitor who is not signed in the first account offered, what it signs in with, and links to the sign-in page (`@demo.notice.*`, fr and en): a site that includes the banner has nothing to add. An account whose roles reach `base.demo.admin_roles` (`[ROLE_ADMIN]`, through the hierarchy too) is still created by the fixtures, but in `demo` it is offered nowhere (`DemoAccountRegistry::offered()`, `demo_accounts()`, `/login/demo`) and signs in with `base.demo.admin_password` (the `DEMO_ADMIN_PASSWORD` secret) or not at all - **a site whose demonstration showed its back office at a click sets that secret, or `admin_roles: []` to keep it public**. `Base\Subscriber\DemoSuperAdminSubscriber` is `DemoBackOfficeSubscriber` (both tiers, a secret each).
- **In the demonstration the back office opens to its own accounts alone, and every role has its account.** Any account signed in below `base.demo.admin_roles` - whatever its roles give it elsewhere, a `ROLE_STAFF` reading orders under `/admin` - gets a 403 on every page of the back office (`DemoBackOfficeSubscriber::onRequest()`). `DemoAccountRegistry::missingRoles()` names the roles of the application's `user_role` type below the back office that no account offered holds (`ROLE_USER`: any account); `demo:reset` warns about them, and a site's suite asserts there are none.
- **The cookies are `@glitchr/flowjs/cookie`** (flowjs 1.2.0) in place of `@glitchr/cookie`: `assets/base-async.js` imports it, `package.json` takes `@glitchr/flowjs` `^1.2.0` and drops `@glitchr/cookie`, `yarn.lock` follows (edited by hand: the cookie entry out, the flowjs one at 1.2.0). The `Cookie` global keeps its methods and its `localStorage` keys; it now knows categories of consent, what each stores, and writes nothing under a refused one (omnibase/consent hands it the site's categories and shows the detailed section). `base-async` therefore packs flowjs's core again, and runs before a site's entries: **a site takes `@glitchr/flowjs` 1.2.0 or later too** (the first `Flow` loaded is everyone's), drops `@glitchr/cookie` from its `package.json` and any import of it. Compiled assets rebuilt (only `base-async` changed).
- `@glitchr/flowjs` is 1.2.1 (`package.json` `^1.2.1`, `yarn.lock` edited by hand: only the flowjs entry, registry tarball and integrity). `base-async` packs flowjs's core and runs before a site's entries, and `Flow` and `Cookie` are the first loaded: on every page the cookie module that ran was the core's 1.2.0, and 1.2.1's corrections (`durations`, an expired answer asked again, `bare`, several places for the banner, the countdown waiting while the banner cannot be seen, `Cookie.use()` before its category) reached no site. Compiled assets rebuilt (only `base-async`'s script changed). **A site takes `@glitchr/flowjs` `^1.2.1` too** (front-end.md, scaffold.md).
- `@glitchr/flowjs` is 1.3.1 (`package.json` `^1.3.1`, `yarn.lock` edited by hand: only the flowjs entry, registry tarball and integrity). The cookie module that runs on every page is `base-async`'s: 1.3.1 brings its `ignore` option (a click inside matching elements - Symfony's web debug toolbar, which omnibase/consent passes - neither answers the question by `dismiss` nor counts as one elsewhere; `Cookie.ignored()`); 1.3.0's `up` and `down` options of the stops are a site's own imports'. Compiled assets rebuilt (only `base-async`'s script changed). **A site takes `@glitchr/flowjs` `^1.3.1` too** (front-end.md, scaffold.md).
- **The server writes no cookie the visitor refused.** `Base\Security\CookieAnswer::of($category)` / `::refused($category)` read the answer the page's script keeps (the cookie `cookie-consent` of `@glitchr/flowjs/cookie`: one yes or no per category); `app.site.is_newcomer()` no longer sets `USER/LAST_VISIT` for a visitor who refused `USER`, who is then a newcomer at each page.
- **The time zone is the visitor's to refuse apart.** `assets/styles/js/user.js` writes it in `LOCATION/TIMEZONE` when the site declares a `LOCATION` category for its visitors to answer (omnibase/consent's default), and leaves it in `USER/INFO` (`timezone`) otherwise, as before; it writes again at each answer (`cookie:consent`), so that what is granted is written at once. `User::getCookie('timezone')` reads `LOCATION/TIMEZONE`, else `USER/INFO`'s. `getCookie('country')` is read from the zone at each call and no longer written back into `USER/INFO` by the server (a cookie written behind the visitor's answer). Docs: `docs/40-commons/front-end.md` (Cookies), `docs/20-architecture/time.md`.
- `@glitchr/flowjs` is taken from npm, `^1.0.1` (`package.json`, `yarn.lock`), in place of the git commit of 1.0.0. **The core's bundle no longer imports `@glitchr/flowjs/ajaxer`**: nothing in the core, in the `omnibase/*` bundles or in the sites calls `Ajaxer` but latoucheoriginale (its own controllers), and the import also brought a second copy of flowjs's core (`Flow`) into `base-defer` on top of the one each site packs with its own imports (only one `window.Flow` lives at run time, the first loaded, but the bytes were sent twice). A site that sends named AJAX channels imports `@glitchr/flowjs/ajaxer` in its own `app-defer.js`. `Base.boot()` starts `Sticky.ready()` and `Transparent.ready()` only if their globals are there and the option is not `false` (`sticky: false`, `transparent: false`; the stops need `stops` not `false` and their global): a site that imports the navigation alone starts the navigation alone. Compiled assets rebuilt.
- The front-end libraries `@glitchr/stickyjs`, `@glitchr/transparentjs` and `@glitchr/ajaxer` are one library, `@glitchr/flowjs` 1.0.0 (pinned to its commit until it is on npm): `base-defer.js` imports `@glitchr/flowjs/ajaxer`, `package.json` and `yarn.lock` name flowjs, `assets/boot.js` and the docs speak of it. `Base.boot()` no longer attaches the stops again after each navigation: flowjs measures them again at each page it swaps in, their options kept. The globals and their options are unchanged; compiled assets rebuilt.
- A guarded form shows its captcha only after a few refused tries: `base.guard.captcha_after` (3) and the option `guard.captcha_after`, counted per form and visitor (address, else session) in `cache.app` for 15 minutes, forgotten after a form sent; below, no widget printed and no token asked. `0` keeps the captcha always shown, as before. Below the threshold the two fields (`guard_captcha`, its fallback) are removed from the form's view rather than marked rendered: a template that prints `form_row(form.guard_captcha)` no longer throws "has already been rendered", `form.guard_captcha is defined` is false, and the submission is read as before.
- The forms' guard speaks to `glitchr/omnishield`, the family formerly named `glitchr/omniguard` (renamed on 2026-10-10: the vendor `omniguard` on Packagist belongs to another project): `omnishield/*` in `suggest` and `require-dev`, `Omnishield\` classes, the configuration `omnishield:`, the test token `omnishield-fixed-token`. `FormGuard`, the option `guard` and `base.guard` keep their names.

### Changed
- License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later. `COPYING` and `COPYING.LESSER` removed, `LICENSE` holds the MIT text.

### Added
- The `demo` environment (`docs/20-architecture/demo.md`): demonstration
  accounts declared by the bundles and the application
  (`Base\Demo\DemoAccountProviderInterface`), created by the fixtures
  (`DemoAccountFactory`), offered at a click on the sign-in page
  (`@Base/demo/_accounts.html.twig`, a POST with a CSRF token); the banner
  (`@Base/demo/_banner.html.twig`); `demo:reset` under `symfony/lock`; no
  e-mail sent, `X-Robots-Tag: noindex`, credentials of a demonstration
  account locked, the staff's mandatory second factor lifted for it, the
  super-administrator signed in by a secret or not at all, and a kernel that
  refuses to start in debug or on the production database. Nothing of it is
  registered in any other environment.
- Standalone Docker demo (root `Dockerfile` + `example/app/`): bare Symfony
  skeleton + this bundle + SQLite, one flat page touring the App\ ↔ Base\
  mirroring, entity layer, setting bag, obfuscator and translator. The
  `example/app/` overlay doubles as a minimal host-app reference config.

### Fixed
- **The folders of `base.attributes.paths` are read.** The AttributeReader picked them out of the parameters with a pattern that wanted a dot after the item's number (`base.attributes.paths.0.`), which no key has: none was ever read - omnibase/marketplace's `src/Attribute`, which its extension prepends (`OrderReference`), and an application's - and `getPaths()` answered `[]` whatever was added. `AttributeReader::configuredPaths()` reads the list whole (`base.attributes.paths`) and item by item (`.0`, `.1`: the flattened parameters, and what the hand-built entity manager gives); `addPath()` keeps the folder. In the harness `OrderReference` is now among the reader's attributes (it fills an order's reference only when none is set). Test: `tests/Attributes/AttributePathsTest`.
- **A route the router does not know is said so without a deprecation.** `AdvancedUrlGenerator::resolveCandidates()` took the first of the route's group, null for an unknown route, and gave it to `str_ends_with()` and `substr()` (deprecated since PHP 8.1) before `RouteNotFoundException`: a page asking whether a route exists (the screens' context, `app_index`) printed two notices. The route's own name stands for its group. Test: `tests/Routing/FirstAddressTest::testAnUnknownRouteIsSaidWithoutADeprecation`.
- **A route's address is the same the first time it is made and every time after.** `Base\Routing\Generator\AdvancedUrlGenerator::generate()` handed the first address of a route in its cache's life through `sanitize_url()` - a slash put before the query (`/chroniques/?page=2`), the root's slash dropped (`http://localhost`) - and every later one, found in its cache, as the router wrote it (`/chroniques?page=2`, `http://localhost/`): a page's links depended on the order Twig or a controller made them in (lesringostars' screens added their query by hand for it; omnibase/blog's post page had its first tag pill at `/chroniques/?sujet=...`). The first one is now as the router wrote it too; in a site whose route cache is warm, nothing changes. Test: `tests/Routing/FirstAddressTest`.
- **`base.extension.empty_trash` defaults to `"+7 days"`, and a bare number is a number of days.** The default was `5`, which `TrasheableSubscriber` gave to `new DateTime()`: on a host without its own value (the harness; the sites set `"+7 days"`), removing a Thread threw `DateMalformedStringException` instead of putting it in the bin. `TrasheableSubscriber::expiry()` reads `5` or `"5"` as `"+5 days"` and keeps a relative date as it is, for the configuration and for `#[Trasheable(expiry: ...)]`; the hand-built entity manager (`Base\Bridge\Doctrine\Mapping`) passes the parameter through it too. Test: `tests/Database/Entity/Extension/EmptyTrashTest` (the readings; a Thread removed in the harness is in the bin until seven days from now).
- **A notification's e-mail without an HTML template of its own is written in the e-mail frame** (`@Base/notifier/email.html.twig`, `NotificationMessages::FRAME`), as one whose template is not found: `asEmailMessage()` gave Twig the empty name and failed (`UnexpectedValueException: Failed to render template ""`), seen in step C3f. **`removeAttachment()`, `removeRecipient()` and `removeHtmlParameter()` take out what they are given**: each called `array_remove()` and dropped its result, so removed nothing; an attachment is removed when it is the very file given (two `\SplFileInfo` compare equal whatever their paths), a recipient by `array_remove()`'s comparison, an HTML parameter by its key (it was looked for among the values).
- **An editor offers live collaboration only where a relay is configured.** The blog's post screen (and the FAQ's, the classroom's cards, sessions and sequences) asks for `collab_live`; on a site without a relay (`COLLAB_TICKET_SECRET` and `COLLAB_RELAY_WS_URL` unset: monica, wedding, wineseller, juriste, panoboutique, omniscient) the editor still asked `/ux/editorjs/collab-ticket` for a ticket on every edit screen, which answered 503 ("Collaboration relay is not configured.") and left an error in the console. `EditorType` and `FormTypeCollabExtension` take `CollabTicketFactory` and drop `collab_live` / `collab: "live"` when `isConfigured()` is false: the editor keeps its autosave and asks for no ticket, a field keeps no presence attributes; with a relay nothing changes. Test: `tests/Field/EditorTypeCollabRelayTest.php`.
- **The fields that name no label are in the back office's language, those of a translation too.** A field without a label shows its name humanised and the theme translates it in the field's own domain, `fields`, where "Title", "Slug", "Headline", "Excerpt", "Content" are not: the page screen (`PageCrudController`) of a French back office read them in English, the fields of each language included. `FormTypeExtension::inheritTranslationDomain()` looks the humanised name up as it does a label, and takes the domain of the form above that has it (the back office's `forms`, `FieldFormBuilder`), two forms below for a `TranslationType`'s fields. `forms+intl-icu.{fr,en}.yaml` gain "Headline" ("Sous-titre"). Test: `tests/Form/PageTranslationLabelsTest.php` (a French page form has no English label, an English one has them).
- **A guarded form's captcha comes with its script** (the sign-in after `base.guard.sign_in_after` failures, a form past its `captcha_after`): the page printed `<altcha-widget>` without `/omnishield/altcha/3.3.0/altcha.min.js`, the widget never solved and the form could not be sent. `FormProcessor::handleRequest()` makes a view to collect the page's assets, and the captcha's field printed its widget through the page's printer as that view was built, script and all; the view the page showed had the element alone. glitchr/omnishield 76dd63a prints a field's widget when the page shows it (`Omnishield\WidgetHtml`); `FormTypeGuardExtension` wraps it as it is, the captcha that waits for consent in its `<template>`, its fallback in its `<div>`, so that this view does not print it either (a string of an older omnishield is wrapped as before). **A site takes glitchr/omnishield 76dd63a or later.** Test: `tests/Service/FormGuardTest` (a view built and thrown away, then the one shown: the captcha and the fallback beside a third party's carry the script).
- **The back office's screens of groups and permissions name their fields** in the language of the screen (`@admin.fields.group.*`, `@admin.fields.permission.*`, fr and en): they read the properties' names, "Tag", "Empowered roles", and "Gid" above a permission's group picker, now "Groupes".
- **A group is listed by its name, a permission by its tag.** `Group` and `Permission` had no `__toString()`, so a picker (`/ux/autocomplete`: a permission's groups, a group's permissions, a user's groups) fell back to the noun of the class and the id - and for an application's subclass, which names no noun of its own, to the translation key: "occasion_group._singular #1" on wedding, "genealogy.tree_group._singular #1" on genealogist. `Group::__toString()` is its name, `Permission::__toString()` its tag (`#id` when empty). A record without a name of its own is listed by the noun of its class, else of the nearest parent class that has one, else by its short class name, never by the key (`Autocomplete::entityNoun()`). Test: `tests/Service/Model/AutocompleteEntityLabelTest`.
- **A page, an attachment or a short link that does not exist answers 404**, the site's error page: `WidgetController` (`/page/{slug}`, `widget_page`; `/attachment/{slug}`, `widget_attachment`) and `ShortLinkController` threw php-http's `Http\Discovery\Exception\NotFoundException`, no HTTP exception, and every unknown address under them answered 500 (a site's link to `/page/gdpr`, a page it never made). They throw `createNotFoundException()`. Tests: `tests/Http/WidgetPageHttpTest.php`, `ProdEnvironmentHttpTest::testAnUnknownPageAnswers404()`.
- **A picker searches an entity whose repository is a plain Doctrine one.** The autocompletion (`/ux/autocomplete/{data}`) asked the entity's repository for `cacheByInstanceOf()`, which only omnibase's `Base\Database\Repository\ServiceEntityRepository` answers: on a bundle's DoctrineBundle `ServiceEntityRepository`, or the `EntityRepository` an entity inheriting another's mapping gets when it names none (omnibase/classroom's levels, subjects and purposes, sequences, resources), every search answered 500 and the picker stayed on "Searching...". `AutocompleteController::repository()` builds one of omnibase's for that class (the controller is given `doctrine`); `ServiceEntityParser` reads the class a repository was built for (`getClassName()`), no longer the one its own name implies (`getFqcnEntityName()`, unchanged for every repository that names none). Test: `tests/Controller/UX/AutocompletePlainRepositoryTest.php`.
- **A picker's search takes a space.** `SelectType` gave select2 its separators (a space, a comma, a semicolon) whether the select takes tags or not; without tags, select2's tokenizer threw at the first separator typed (`_removeOldTags is not a function`) and the search stopped: "Cours élémentaire" found nothing. The separators go to a select of tags only. Test: `tests/Field/SelectTypeTokenSeparatorsTest.php`.
- The icon of a taxon, a tag, a permission and an attribute adapter (`AdapterCrudController`) is optional in the back office, as its column: a taxon without one (omnibase/classroom's levels and subjects, a blog's topics) could not be saved again as it was ("This value should not be blank" on Icon). Test: `tests/Controller/OptionalIconTest.php`.
- **A taxon is made with the label of the site's language alone.** `TaxonCrudController` asked the label in every language (`'label' => ['required' => true]` on each translation), so no level, subject, FAQ section or product category could be saved with one label. The label is asked in the default language only (`'required' => fn (bool $required, string $locale, array $options): bool => $required`, the form of `TranslationType`'s closure; an empty translation is dropped), as an attribute adapter's (`AdapterCrudController`). The slug follows that label as it is typed (`setTargetFieldName('translations.label')`). A taxon without any label is refused on the server too (`Taxon::validateLabel()`, "This value should not be blank." on its translations): only the browser's `required` stopped it, and it was saved with no name. Test: `tests/Controller/TaxonLabelTest.php`.
- **The fields that name no label are translated.** A field without a label shows its property humanised ("createdAt" -> "Created at"), translated through the `forms` domain by the form, the list's columns and its filters; "State" and "Slug" were in no language, so every `StateField` and `SlugField` of every bundle read English, as did the other fields and filters of omnibase's own screens (Icon, Created at, Locked, Roles, Plain password...). `forms+intl-icu.{en,fr}.yaml` carry them, and the names the bundles' screens share (Status, Title, Published at, Featured, Visible...): a site's own catalogue still prevails. Test: `tests/Translation/FieldLabelsCatalogueTest.php` (every label omnibase's screens leave to their property, in each language of the domain).
- **A site behind a proxy that terminates TLS is https, on its port.** FrankenPHP sets `HTTPS` to `""` (empty, not missing) behind such a proxy; `get_url()` read `$_SERVER['HTTPS'] ?? ...`, the empty string stopped the chain, and every address it gave was http - with the router's ports (`HTTP_PORT`), on a port other than 443 every page redirected to port 80. `get_url()` reads the kernel's main request now (else a request made of PHP's globals): the scheme is `Request::isSecure()` (a trusted proxy's `X-Forwarded-Proto` counts, `HTTPS` `""` or `"off"` is http), the host `Request::getHost()`, the port the forwarded host's (`X-Forwarded-Host`, the Host the browser sent) else `Request::getPort()` (`X-Forwarded-Port`); without a trusted proxy (`framework.trusted_proxies`), the Host header's port else `SERVER_PORT`, and without `HTTPS` at all `USE_HTTPS`, `REQUEST_SCHEME` and `X-Forwarded-Proto`, as before. `get_url_scheme()` and `get_url_port()` say each apart; `compose_url()` takes its default scheme from the first (it read `HTTPS` `"off"` as https). The forwarded host's port comes before `X-Forwarded-Port` because a proxy in a container published on another port (443 published on 8663) often sends its own. A kernel that shuts down empties its request stack (`BaseBundle::shutdown()`): a request a test pushed and never popped would otherwise remain the current address after the kernel is gone. Test: `tests/Routing/ForwardedHttpsUrlTest.php`.
- **No address carries a basic authentication's credentials.** The address to come back to after signing in (`Referrer::getUrl()`) was composed again from the stored one with its user (`https://user@host/...`); it no longer keeps a user or a password. `get_url()`, `compose_url()` without a user, the absolute links and the redirection to the sign-in page never carried them: the server's `Location` is `/rescue`, and a client that resolves it against an address holding `user:password@` (curl, a browser opened on such an address) writes them back itself. Test: `tests/Http/BasicAuthUrlHttpTest.php` (a request with `Authorization`, `PHP_AUTH_USER` and `PHP_AUTH_PW`: the redirection, the current address, an absolute link, the address to come back to).
- **A site served on a port of its own keeps it.** With the reduction (`HTTP_REDUCTION`), `RouterSubscriber` rebuilds the visited address with `AdvancedRouter::getPort()`, which judged the address's port by the router's scheme, `getScheme()`: https whatever the request (`REQUEST_SCHEME` "http" is a truthy string). The port of an http address was checked against `HTTPS_PORT` (`[443]` when unset): a site on http:8081 (`HTTP_PORT='[8081]'`) lost its 8081 and every page redirected to port 80. Since `get_url()` reads the request the kernel handles, a site's suite saw it too (latoucheoriginale's signed invoice link, `http://localhost:8081/factures/...`, answered 302 to `http://localhost/factures/...`); before, `get_url()` read PHP's globals, which a test sets without a port. `getPort()` judges the port by the scheme of the address it reads it from: a port the site declares for that scheme (`HTTP_PORT` for http, `HTTPS_PORT` for https) is kept, any other is replaced as before (the fallbacks, then the first declared port). Test: `tests/Routing/NonDefaultPortUrlTest.php`.
- `composer.json` now declares dependencies the bundle's DI actually
  hard-references: `hwi/oauth-bundle`, `symfony/webpack-encore-bundle`,
  `symfony/monolog-bundle`, `glitchr/ux-google`, plus `ext-imagick` /
  `ext-igbinary` (required unconditionally in `BaseBundle::boot()`).

## [3.0.0] — 2026-07-04

First tagged release of the 3.0 line (branched from 2.0 in 2025).

### Added
- Offline mode: YouTube-style offline page takeover.
- Turbo enabled across admin; admin UX fixes.

### Changed
- Upgraded to Symfony 8.0 (from 6.x/7.x line); EasyAdmin 5 compatibility.
- Asset pipeline optimized (async/defer entrypoints).

### Fixed
- Stale `dirty-form` `onbeforeunload` handler lingering across Turbo visits
  in the admin (spurious "Leave site?" dialogs).
- CSP: removed `csp_nonce('script')` from admin layout and CRUD templates.
- SF8 compatibility: replaced removed `Request::get()` usages.
- EasyAdmin CRUD form deprecations, admin context factory/override updates.

### Repository
- License texts added (`COPYING`, `COPYING.LESSER` — LGPL-3.0-or-later).
- `.php-cs-fixer.cache` untracked; `.gitignore` cleaned up.
- Tests bootstrap (PHPUnit 10) and GitLab CI (php-cs-fixer, PHPStan, PHPUnit).
- History slimmed: built assets under `public/` removed from past revisions.

## [1.3] — 2023-08-19 · [1.2] — 2023-06-27 · [1.1] — 2023-04-24 · [1.0] — 2023-04-03

Historical tags of the 1.0 line (Symfony 6). See `git log 1.0..1.3` for details.
