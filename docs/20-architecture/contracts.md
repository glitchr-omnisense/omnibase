---
title: The contracts of the Foundation
order: 27
---

# The contracts of the Foundation

What omnibase's framework-free code (an entity's getter, an attribute, a value
object) asks of the application it runs in, by an interface, never by a
Symfony service (plan "omnibase agnostique", step C4). Each is reached through
one registry, `Base\Contract\Runtime`, which the framework fills at boot.

**The host's contracts are omnisense/omnibridge's** (plan "omnibase agnostique", A2).
What any host answers - addresses, translations, languages, access, sessions, tokens,
stored files, routes, environment - lives in the omnibridge family's core,
[omnisense/omnibridge](https://github.com/glitchr-omnisense/omnibridge), as
`Omnibridge\Contract\*`, with the registry itself (`Omnibridge\Runtime`, its
`MissingContractException` and `SystemClock`). What describes omnibase's own models stays
here as `Base\Contract\*`: `Nouns`, `Media`, `Pictures`, `Texts`, the screens'
`ContextProvider`, the user's `SecurityUser`/`TwoFactorUser`, its repository's `SecurityUserLoader`/`SecurityUserProvider` and the passkeys' repositories' `PasskeyRecords`/`PasskeyUsers`. `Base\Contract\Runtime` is
omnibridge's registry (the same state) with omnibase's accessors beside it: `parameters()`,
`nouns()`, `media()`, `pictures()`, `texts()`, `mailer()`. The former names
(`Base\Contract\Urls`... and omnibridge/twig's `Omnibridge\Twig\Contract\Urls` and
`Translations`), aliases of the moved interfaces since A2, were removed at omnibase 4's final
pass: name `Omnibridge\Contract\*`.

| Contract | What it answers | Under Symfony |
|---|---|---|
| `Omnibridge\Contract\Urls` (formerly `Base\Contract\Urls`) (omnibridge/twig's `Urls`, whose `url()` it declared itself before omnibridge/twig d786505) | `path()` and `url()` of a route, `asset()` of a public file | omnibridge/symfony's `Omnibridge\Symfony\RouterUrls` (formerly `Base\Bridge\Symfony\RouterUrls`, step A1): `advanced_router`, the asset packages - Twig's `path()`, `url()`, `asset()` |
| `Omnibridge\Contract\Environment` (formerly `Base\Contract\Environment`) (step C4b) | the environment's name (`prod`, `dev`, `test`), debug, the application's secret | omnibridge/symfony's `Omnibridge\Symfony\Contract\KernelEnvironment` (formerly `Base\Bridge\Symfony\KernelEnvironment`): `kernel.environment`, `kernel.debug`, `kernel.secret` - `BaseService::getEnvironment()`, `isDebug()`, `getSalt()` |
| `Base\Service\ParameterBagInterface` (step C3c; in the registry since C4b) | the application's parameters by a dotted path | `parameter_bag`: `HotParameterBag`, the settings stored in the database over the container's - `BaseTrait::getParameterBag()`, and the autowiring of this interface since C9; where there is no container, `Base\Service\ArrayParameterBag`, and `Base\Service\HotParameters` over it for the settings (below) |
| `Omnibridge\Contract\Routes` (formerly `Base\Contract\Routes`) (step C4b) | what the routes are beyond their addresses: a route's declared path (`pattern()`), the route an address leads to (`match()`), a route's icons | `Base\Bridge\Symfony\RouterRoutes`: `advanced_router` (its route collection, `getRouteName()`) and the `IconProvider` |
| `Omnibridge\Contract\Translations` (formerly `Base\Contract\Translations` and omnibridge/twig's `Translations`, one interface since A2) | `trans()`, and whether the catalogue holds a key (`exists()`), for a code that tries several keys | omnibridge/symfony's `Omnibridge\Symfony\TranslatorTranslations` (formerly `Base\Bridge\Symfony\TranslatorTranslations`): the same chain - `Base\Service\Translator::transExists()` |
| `Base\Contract\Nouns` (step C4b) | what an entity is called: its noun (`entity()`), singular or plural, a gender, a level of politeness, a property's label; `entityExists()` | `Base\Service\Model\EntityNouns` from `Base\Service\Translator::getEntityNouns()`: the rule `transEntity()` answers by |
| `Omnibridge\Contract\Access` (formerly `Base\Contract\Access`) | `isGranted()` an attribute, on a subject (a `Thread`...), `getUser()` | omnibridge/symfony's `Omnibridge\Symfony\Contract\SecurityAccess` (formerly `Base\Bridge\Symfony\SecurityAccess`): the authorization checker and the token storage - `BaseService::isGranted()`, Twig's `is_granted()` |
| `Omnibridge\Contract\Sessions` (formerly `Base\Contract\Sessions`) (step C4b) | a user signed out when it is the visitor signed in (`signOut()`), false otherwise | omnibridge/symfony's `Omnibridge\Symfony\Contract\SecuritySessions` (formerly `Base\Bridge\Symfony\SecuritySessions`): the token storage, the `REMEMBERME` cookie on the path and the site's domain |
| `Omnibridge\Contract\Locales` (formerly `Base\Contract\Locales`) | the current locale, the default, the available ones (`fr-FR`) | `Base\Bridge\Symfony\LocalizerLocales`: the `localizer` |
| `Omnibridge\Contract\Files` (formerly `Base\Contract\Files`) | a stored file's public path and web address, its local path, whether its storage is remote | `Base\Bridge\Symfony\FlysystemFiles`: `flysystem`, and the image route for a remote storage, as the uploader resolves it |
| `Base\Contract\Media` (step C4b) | the image route for a stored image: the extension it is served with, its obfuscated `data`, the route's address | `Base\Bridge\Symfony\ServiceMedia`: `MediaService` (`getExtension()`, `obfuscate()`, `generate()`) |
| `Base\Contract\MediaRouter` (audit R6) | what the media rule asks a router: a named route's address, an address's route and parameters, its name, a public file's address, an address as it is - `FileService`, `MediaService` and `Base\Service\ImageRoute` (the image route's answers, `ROUTES`) take it | `advanced_router` (`AdvancedRouterInterface` extends it); omnibridge/laravel's over Laravel's router |
| `Base\Contract\Pictures` (step C5) | an image's address at a size, what `\|picture` answers | `Base\Bridge\Symfony\MediaPictures`: `CommonsTwigExtension::picture()` (MediaService's thumbnails) |
| `Base\Contract\Texts` (step C5) | a stored text rendered (Editor.js or HTML), its table of contents, whether it is Editor.js | `Base\Bridge\Symfony\WysiwygTexts`: the pipeline of `\|wysiwyg` and `\|wysiwyg_toc` |
| `Omnibridge\Contract\Tokens` (formerly `Base\Contract\Tokens`) (step C5) | the token a form carries for an intent (`csrf()`), what a screen's actions send back | omnibridge/symfony's `Omnibridge\Symfony\Contract\CsrfTokens` (formerly `Base\Bridge\Symfony\CsrfTokens`): the CSRF token manager - Twig's `csrf_token()` |
| `Psr\Clock\ClockInterface` (PSR-20) | `now()` | the framework's `clock` (symfony/clock); the machine's (`Omnibridge\SystemClock`) when there is none |
| `Psr\EventDispatcher\EventDispatcherInterface` (PSR-14) | `dispatch()` | `event_dispatcher` |
| `Symfony\Component\Mailer\MailerInterface` | `send()` | `mailer.mailer` |

`Locales` is plural, as omnibridge's `Urls` and `Translations` are, and so that
importing it never shadows PHP's `\Locale`.

**A token per intent, under Symfony only.** Symfony's CSRF tokens are one per intent
(`2fa_disable`, `passkey_delete_12`...): a screen's action carries the token of its own
intent, and the controller checks that one. Laravel has a single session token for every
intent: an action pointing at a Symfony route, rendered by Laravel, would carry a token
Symfony refuses. Nothing does that today - the Laravel twin shows no account page; a
framework rendering another's actions binds `Tokens` to the other's tokens, or posts to its
own routes.

**No contract for the mail.** symfony/mailer is a standalone component, allowed
in the Foundation (decision D1), and Laravel's own mail is built on it: its
`MailerInterface` is the contract, and another framework hands a `Mailer` over
its own transport.

## BaseTrait and BaseService: the application's shortcuts (step C9)

**The rule: BaseTrait to go fast in an application; the contracts in the core and the
bundles.** `Base\Traits\BaseTrait` (static accessors usable from an entity, an attribute,
a subscriber) and `Base\Service\BaseService` (its facade) are the tool for writing an
application quickly, and they stay - neither is deprecated, nor removed in 5.0. The core and
its bundles add no new use of either: a class of theirs asks `Base\Contract\Runtime` (or is
given the service it needs), so that it runs where there is no Symfony.

What changed in C9:

- **BaseService is a lazy service.** The container hands out a ghost of the class (PHP 8.4's
  lazy objects, not a proxy subclass), built with its 23 services the first time one of its
  methods reads a property. Injected in a class that never calls it, it builds nothing.
- **Nothing on a page's path injects it any more.** `SiteVariable` and `AdminVariable`
  (`app.site`, `app.admin`) and `EmailVariable` read the settings from the `SettingBag` and
  the route index from the router; `FormTypeBootstrapExtension` the parameters;
  `ErrorController` the `Environment` contract (`Base\Service\Model\DevelopmentEnvironment`,
  `isDevelopment()`'s rule); `MainController` and `UserSettingsController` never called it.
  The core's `getService()->isDebug()`, `getSalt()`, `isGranted()`, `isEntity()` calls are
  `Runtime::environment()->isDebug()`, `->secret()`, `Runtime::access()->isGranted()`, and
  BaseTrait's static `isEntity()` (the class metadata manipulator).
- **What a contract answers, they ask the contract** when the registry has it, so they answer
  outside Symfony too, and the methods below read no property of BaseService - called on the
  ghost, they build nothing:

| Accessor | Asks | Else (no contract in the registry) |
|---|---|---|
| `BaseTrait::getParameterBag()` | `Runtime::parameters()` (under Symfony `parameter_bag`, the same object) | the `base.runtime` locator's `parameter_bag` |
| `BaseTrait::getEnvironment()` | `Runtime::environment()->name()` | the kernel's environment, seeded at boot |
| `BaseService::isDebug()` | `Runtime::environment()->isDebug()` | the kernel |
| `BaseService::isDevelopment()` | `DevelopmentEnvironment::is(Runtime::environment())` | the same rule on the kernel |
| `BaseService::getSecret()`, `getSalt()` | `Runtime::environment()->secret()` | `kernel.secret` |
| `BaseService::isGranted()` | `Runtime::access()->isGranted()` | the authorization checker |

  The other accessors hand out the framework's services, typed as they always were
  (`getRouter()`, `getTranslator()`, `getLocalizer()`, `getTwig()`, `getTrading()`,
  `getDoctrine()`, `getTokenStorage()`...): no contract has their type, so they stay on the
  lazy `base.runtime` locator, one service built on its first call.

**The bundles' uses** (for step C8, which moves them to the contracts; nothing changed in the
bundles): no bundle injects BaseService or calls `getService()`. Fourteen entities use
BaseTrait - marketplace's `Order` (`getTrading()` x8, `getTranslator()` x3, `getRouter()`,
`getParameterBag()`), `Product` (`getParameterBag()`, `getRouter()`, `getTranslator()`,
`getTwig()`), `Order\OrderItem` (`getTrading()` x3), `Order\Method\PaymentMethod`
(`getParameterBag()`), `ShippingMethod` (`getParameterBag()` x2),
`Sales\Attribute\Rule\TotalPriceAdapter` (`getTrading()`); forum's `Category`
(`getRouter()`); and, calling none of its accessors, marketplace's `Sales\Discount`, `Fee`,
`Sales\Attribute\Action\FixedAmountAdapter`, forum's `Post`, mailbox's `Conversation`,
classroom's `Entitlement` and `Resource`.

## The parameters and the settings over them (step C9)

`Base\Service\DottedParameterBag` (Foundation) reads parameters given by their full names
(`base.user.active_delay` => 300) by a dotted path - a path naming the values under it
(`base.user`) gives them all. `ArrayParameterBag` (an array) and `ParameterBag` (the Symfony
container's parameters, and DependencyInjection's two interfaces) read through it.
`has()` is whether `get()` finds a value: a parameter set to `false`, `0` or `""` is there,
one set to `null` is not (it was the value's truth).

`Base\Service\HotParameters` (Foundation) lays the settings stored in the database - a back
office's setting linked to a parameter - over any `ParameterBagInterface`. `get()` reads, in
this order, by the presence of a value:

1. the `$bag` given to the call,
2. the settings (`add()`; when the overlay is on and `$useHotBag` is true),
3. the configuration.

A setting stored as `false` wins over the configuration's `true`; a setting whose value is
`null` (none was typed) is no value, the configuration's is read. The overlay is on when
`base.parameter_bag.use_hot_bag` is `true`; off, the configuration is read (it read nothing
at all before: every parameter was `null` unless a `$bag` was given). `has()` reads the same
sources in the same order.

Under Symfony `parameter_bag` is `HotParameterBag`, that overlay over `ParameterBag` as
DependencyInjection's `ParameterBagInterface` and `ContainerBagInterface`; it refuses
`clear()` and `remove()` as the frozen bag does, and `HotParameterBagSubscriber` gives it the
settings on the first request or command. `parameter_bag`, the autowiring of
`Base\Service\ParameterBagInterface` (which gave the plain `ParameterBag`, without the
settings: an autowired `EditorController`, `AttributeReader` read the configuration alone),
DependencyInjection's interfaces, `Runtime::parameters()` and `BaseTrait::getParameterBag()`
are one object (`ParameterBagServicesTest`). Elsewhere:
`new HotParameters(new ArrayParameterBag([...]), true)` and `add()` the settings.

`Base\Service\SettingBag` (the settings themselves) keeps its snapshot in a PSR-6 pool
(`Psr\Cache\CacheItemPoolInterface`, no longer Symfony's `CacheInterface`); its cache warmer
is `Base\Bridge\Symfony\SettingBagWarmer`. It is in the Foundation: Doctrine and psr/cache.

## The registry

```php
use Base\Contract\Runtime;

Runtime::urls()->path('blog_article', ['slug' => $slug]);
Runtime::access()->isGranted('ROLE_ADMIN');
Runtime::translations()->trans('@entities.article._singular', [], null, Runtime::locales()->getLocale());
```

- **Filled at boot.** `BaseBundle::boot()` hands it the `base.contracts`
  locator (`ContractsPass`): each contract by its interface's name, bound to
  the service the container gives that name - an application rebinds one by
  aliasing the interface. Only the contracts whose service exists are listed,
  and a locator holds closures: nothing is built before it is asked. Another
  framework calls `Runtime::setContainer()` with a PSR-11 container whose ids
  are the interfaces' names (Laravel's application is one), or `Runtime::set()`
  one contract at a time (it wins over the container's).
- **Loud when empty.** A contract asked and not filled throws
  `Base\Exception\MissingContractException`, which names it - where a getter
  through `BaseTrait` that found no service returned null in silence.
  `Runtime::find()` answers null instead, for a caller that can do without.
- **One kernel at a time.** A different container forgets what the previous
  one gave (as `BaseCommonTrait::setRuntime()` does); `Runtime::reset()`
  forgets everything (a test's tear-down).

The contracts, the registry, `SystemClock` and the adapters load without the
framework (`tests/Foundation.php`); `tests/BareTest.php` runs
`tests/Contract/runtime-without-framework.php` with the autoloader alone: plain
PHP implementations bound as another framework binds them, and a user of the
contracts that answers through them. `tests/Bridge/Symfony/ContractAdaptersTest`
compares every adapter's answers with the services and the Twig functions they
stand for.

## A link by a router's reference type: `Base\Contract\Link`

The entities' `__toLink(array $routeParameters = [], int $referenceType = ...)`
take Symfony's reference types. `Link::to($name, $parameters, $referenceType)`
makes the address through `Runtime::urls()`: `Link::ABSOLUTE_PATH` (1) is
`path()`, `Link::ABSOLUTE_URL` (0) is `url()` - the values of
`UrlGeneratorInterface`'s constants, named here so that a link is made without
the routing component (`LinkableInterface`'s default is `Link::ABSOLUTE_PATH`).
A relative or a network path has no equivalent in the contract: it is asked of
the implementation's own `generate($name, $parameters, $referenceType)` when it
has one (`RouterUrls`, the router as before) and refused otherwise. It is a
helper, not a contract.

## The entities' getters on the contracts (step C3e)

What an entity of the core asked of `BaseTrait`'s services and now asks of the
registry, with the same answers under Symfony (`tests/Contract/EntityGettersTest`
compares each with the service it used) and answers where there is no framework
(`tests/Contract/entities-without-framework.php`, run by `BareTest`):

| Getter | Before | Now |
|---|---|---|
| `Semantic::__toLink()`, `getUrl()` | `getRouter()->generate()` | `Link::to()` (a route the router cannot make: null, as before; no Urls: `MissingContractException`) |
| `ShortLink::__toLink()` | `getRouter()->generate()` (by `__call`, on the current translation's `BaseTrait`: the Localizer first) | `Link::to()` |
| `Thread::isVisible()` | `getService()->isGranted('ROLE_ADMIN')`, and the owners compared with the token storage's token (never one of them: never true) | `Runtime::access()->isGranted('ROLE_ADMIN')`; the token line dropped, the answers unchanged |
| `User::isGranted($role)` | `getService()->isGranted($role, $this)` | `Runtime::access()->isGranted($role, $this)` (User itself still loads the framework: Security's `UserInterface`, Scheb's) |
| `TranslatableTrait::translate()` (every translatable entity's titles, its `__call` getters and setters), its `__get` and `__isset` fallbacks, `addTranslation()` | `BaseService::getLocalizer()`: `getDefaultLocale()`, `getAvailableLocales()`, `getLocale($locale)`, `getLocaleLang()` | `Runtime::find(Locales::class)` (none: null, as when no Localizer was found) and `LocaleNormalizer` |
| `TranslationTrait::getLocale()`, `setLocale()` | `Localizer::normalizeLocale()` (its statics, filled when the Symfony service is built) | `LocaleNormalizer::here()`: the rule with the Locales contract's default and available locales |

`Base\Service\Model\LocaleNormalizer` is the Localizer's rule for omnibase's
`xx-XX` form, taken out of it: `Localizer::normalizeLocale()`,
`__toLocaleLang()` and `getLocales()` now ask it with the Localizer's own
statics, so the strings are the same by construction; the entities ask it
with the Locales contract's (`here()`). The test keeps a copy of the rule as
it was and compares both paths with it.

## The screens (step C5)

A controller hands a template and a view model (`Base\Screen\View`: template,
`Base\Screen\Screen`, the React component's name, the status) to
`Base\Bridge\Symfony\ScreenResponder` - Symfony's adapter of the framework-free
`Base\Screen\Negotiation` (step R10; omnibridge/laravel's `Screen\Responder` is
Laravel's), which also answers what an action written once for every host hands back:
a `Template` (its data in JSON too when it allows it), `Json` (its templates rendered),
a `Shell` (omnibridge/react's protocol), a `File`, or null for no such page -, which renders it with the request's frame
(`Base\Screen\ContextProvider`, under Symfony `SymfonyScreenContext`: locale, site,
links, viewer, theme, a site's own fields; the flashes) - Twig with `frame` and
`screen`, or omnibridge/react's page in JSON (`X-Omnibridge`; without omnibridge/react,
a request asking `application/json` alone gets the props). A JSON answer carrying a
viewer is private and varies on `Cookie`. The view models (`Base\Screen\*`: Frame, Site,
Viewer, Author, Tag, Link, StoryText, Card, Story, Comment, Reply, Discussion) keep
lesringostars' prop names (`App\Screen\*`, the pilot's); `Base\Screen\Builder\Stories`
makes them from a `Thread` and its comments with the Texts and Pictures contracts and
the clock (`recent`, so that no template compares a moment). All of them are in the
Foundation (`tests/Screen/screens-without-framework.php`).

The account's pages are screens too (C5, its second part): a member's profile, the
settings, the notifications (`Base\Screen\Account\{ProfilePage, SettingsPage,
NotificationsPage}` and their parts), built by `Base\Screen\Builder\Accounts` from the
entities and the security policy, with the Urls and Tokens contracts and the clock (a
notification's `group`, in Twig's time zone), and handed by the controllers with
`Base\Bridge\Symfony\AccountScreens` (what only Symfony knows: the viewer, an avatar's
thumbnail, the providers, web push). A visitor's profile carries what its page shows them,
nothing of the account. The settings' small forms are `Action`s (an address and the token
of its intent); a screen carrying tokens is `Base\Screen\Confidential`, whose answers are
`private, no-store` and vary on `Cookie`. A screen's props never take one of the frame's
keys (`Frame::RESERVED`): the responder refuses it. A template a site overrides gets the
variables of before as well (`ScreenResponder::legacy()`); a host page renders the core's
profile template with the user alone, `account_profile(user)` giving it the model. The
forms (profile edit, sign-in, sign-up, passwords, two-factor setup) stay Symfony's (D3).

## What the classes read through BaseTrait, on contracts (step C4b)

What a class of the core still asked of `BaseTrait`'s services after C3e, now asked of the
registry, in the order lesringostars' pages go through it. Each move keeps the answers under
Symfony (a test compares them with the service's, its old code copied as it was) and gives
answers where there is no framework (`tests/Contract/basetrait-seams-without-framework.php`,
run by `BareTest`). `BaseTrait` itself stays, its accessors' signatures unchanged, for the
bundles and the sites that call them (since C9 the ones a contract answers ask it, above).

| Class | Before | Now |
|---|---|---|
| `Thread::__toString()`, `Widget::__toString()` without a title | `getTranslator()->transEntity(static::class)` | `Runtime::nouns()->entity(static::class)` (`NounsTest`) |
| `Thread::getPublishTimeStr()`, `Token::getRemainingTimeStr()`, `getThrottleTimeStr()` | `getTranslator()->transTime($seconds)` | `Duration::spell($seconds, Runtime::translations())` (`DurationTest`: the texts rewritten in the back office printed as before) |
| `Image::__toLink()` | `getMediaService()->getExtension()`, `obfuscate()`, then `getRouter()->generate()` | `Runtime::media()->extension()`, `obfuscate()`, then `Link::to()` (`MediaTest`) |
| `ImageCrop::__toLink()` | `getMediaService()->getExtension()` (after a `getExtensions()` whose answer it overwrote), `generate()` | `Runtime::media()->extension()`, `link()` |
| `Semantic::getPath()`, `Widget\Route::getPath()` | `getRouter()->getRouteCollection()->get($name)?->getPath()` | `Runtime::routes()->pattern($name)` (`RoutesTest`) |
| `Semantic::getRoute()`, `Widget\Route::getRoute()` | `getRouter()->getRoute($this->getUrl())`: a `Route` object handed to a `?string` return type, a `TypeError` for any route found | `Runtime::routes()->match($url)`: the name of the route the address leads to |
| `Semantic::getRouteIcons()`, `Widget\Route::getRouteIcons()` and its `__iconize()` | `getIconProvider()->getRouteIcons($name)` (every route's icons for no route) | `Runtime::routes()->icons($name)` (null for no route) |
| `Widget\Route::__toLink()`, `getUrl()`, `generate()` | `getRouter()->generate()` | `Link::to()`, as `Semantic` since C3e (a route the router cannot make: null; no Urls: `MissingContractException`) |
| `User::getActiveDelay()`, `getOnlineDelay()` | `self::getParameterBag()->get()` | `Runtime::parameters()->get()` (`EnvironmentTest`) |
| `User::isTester()` | `getService()->isDebug()`, `getService()->getParameterBag('base.notifier.test_recipients')` | `Runtime::environment()->isDebug()`, `Runtime::parameters()->get()` |
| `Token::__construct()` | `getService()->getParameterBag('base.user.token_default_throttling')`, `getService()->getSalt()` | `Runtime::parameters()->get()`, `Runtime::environment()->secret()` (a token is now built where there is no framework) |
| `VaultTrait::setSecure()` | `getEnvironment()` | `Runtime::environment()->name()` |
| `User::getLocale()`, `setLocale()` without a locale of the user's | `Localizer::__toLocale(getTranslator()->getLocale())` | `Runtime::locales()->getLocale()` (`SessionsTest`: the same for every language the site speaks; for a bare language it does not speak, the Localizer's form, `ja-FR`, as the rest of the page, where intl's country came before, `ja-JP`) |
| `User::logout()` | `getTokenStorage()`: the token forgotten, the cookies cleared, through `getRouter()->getDomain()`; else `kick()` | `Runtime::sessions()->signOut($this)`, else `kick()` |

`Base\Entity\User` reads nothing through `BaseTrait` any more (it still uses it: the sites
call its accessors on a user).

**Where C4b stops.** Left on `BaseTrait`, each for a reason: `Notification` (the notifier,
Twig and the project's directory - its message half is already `Bridge\Symfony`, step C3f;
the notifier is a Symfony service by nature), `AbstractExtension::isEntity()` (Doctrine's
metadata, a question for `Bridge/Doctrine`), `Uploader`'s `AttributeReader` (omnibase's own
service, already in the Foundation), `MediaEnhancer` (the media service inside the Wysiwyg
pipeline, which `Texts` already covers from outside), the forms, fields, controllers and
validators (Symfony by nature, decision D3), and the bundles' entities (step C8).

**What an entity is called: `Base\Service\Model\EntityNouns`.** The rule of
`Translator::transEntity()`, `transEntityExists()` and `parseClass()` (the key of a class:
its name less its entity namespace, dotted, in snake case - `Base\Marketplace\Entity\Order`
is `marketplace.order`; then the variants its options ask for, `NounVariants`, the first the
"entities" catalogue holds) moved into a class of the Foundation, built on the Translations
contract and the entity namespaces of the application's bundles. The translator now answers by
an instance of it over itself (`getEntityNouns()`, the bundles `BaseBundle` knows), and that
instance is the Nouns contract under Symfony: one rule, the same strings. A host without the
framework builds one with `new EntityNouns($translations, EntityNouns::namespacesOf([...its
bundle classes]))` and binds it to `Base\Contract\Nouns`.

**A number of seconds in words: `Base\Service\Model\Duration`.** `Translator::transTime()`'s
words (`base.years` to `base.seconds`, ICU plurals; a month of 30 days) over the Translations
contract (`trans()`). Under Symfony that is the `translator` chain, so
a unit the team rewrote in the back office is the one printed, as before; `transTime()` now
answers by it.
