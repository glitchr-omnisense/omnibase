---
title: The contracts of the Fondation
order: 27
---

# The contracts of the Fondation

What omnibase's framework-free code (an entity's getter, an attribute, a value
object) asks of the application it runs in, by an interface, never by a
Symfony service (plan "omnibase agnostique", step C4). Each is reached through
one registry, `Base\Contract\Runtime`, which the framework fills at boot.

| Contract | What it answers | Under Symfony |
|---|---|---|
| `Base\Contract\Urls` (omnibridge/twig's `Urls`, whose `url()` it declared itself before omnibridge/twig d786505) | `path()` and `url()` of a route, `asset()` of a public file | `Base\Bridge\Symfony\RouterUrls`: `advanced_router`, the asset packages - Twig's `path()`, `url()`, `asset()` |
| `Omnibridge\Twig\Contract\Translations` | `trans()`, ICU messages | `Base\Bridge\Symfony\TranslatorTranslations`: the `translator` chain (texts rewritten in the back office, `Base\Service\Translator`, Symfony's) - Twig's `\|trans` |
| `Base\Contract\Translations` (step C4b; omnibridge/twig's `Translations` and `exists()`) | `trans()`, and whether the catalogue holds a key (`exists()`), for a code that tries several keys | `Base\Bridge\Symfony\TranslatorTranslations`: the same chain - `Base\Service\Translator::transExists()` |
| `Base\Contract\Nouns` (step C4b) | what an entity is called: its noun (`entity()`), singular or plural, a gender, a level of politeness, a property's label; `entityExists()` | `Base\Service\Model\EntityNouns` from `Base\Service\Translator::getEntityNouns()`: the rule `transEntity()` answers by |
| `Base\Contract\Access` | `isGranted()` an attribute, on a subject (a `Thread`...), `getUser()` | `Base\Bridge\Symfony\SecurityAccess`: the authorization checker and the token storage - `BaseService::isGranted()`, Twig's `is_granted()` |
| `Base\Contract\Locales` | the current locale, the default, the available ones (`fr-FR`) | `Base\Bridge\Symfony\LocalizerLocales`: the `localizer` |
| `Base\Contract\Files` | a stored file's public path and web address, its local path, whether its storage is remote | `Base\Bridge\Symfony\FlysystemFiles`: `flysystem`, and the image route for a remote storage, as the uploader resolves it |
| `Base\Contract\Media` (step C4b) | the image route for a stored image: the extension it is served with, its obfuscated `data`, the route's address | `Base\Bridge\Symfony\ServiceMedia`: `MediaService` (`getExtension()`, `obfuscate()`, `generate()`) |
| `Base\Contract\Pictures` (step C5) | an image's address at a size, what `\|picture` answers | `Base\Bridge\Symfony\MediaPictures`: `CommonsTwigExtension::picture()` (MediaService's thumbnails) |
| `Base\Contract\Texts` (step C5) | a stored text rendered (Editor.js or HTML), its table of contents, whether it is Editor.js | `Base\Bridge\Symfony\WysiwygTexts`: the pipeline of `\|wysiwyg` and `\|wysiwyg_toc` |
| `Base\Contract\Tokens` (step C5) | the token a form carries for an intent (`csrf()`), what a screen's actions send back | `Base\Bridge\Symfony\CsrfTokens`: the CSRF token manager - Twig's `csrf_token()` |
| `Psr\Clock\ClockInterface` (PSR-20) | `now()` | the framework's `clock` (symfony/clock); the machine's (`Base\Contract\SystemClock`) when there is none |
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
in the Fondation (decision D1), and Laravel's own mail is built on it: its
`MailerInterface` is the contract, and another framework hands a `Mailer` over
its own transport.

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
framework (`tests/Fondation.php`); `tests/BareTest.php` runs
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
`Base\Bridge\Symfony\ScreenResponder`, which renders it with the request's frame
(`Base\Screen\ContextProvider`, under Symfony `SymfonyScreenContext`: locale, site,
links, viewer, theme, a site's own fields; the flashes) - Twig with `frame` and
`screen`, or omnibridge/react's page in JSON (`X-Omnibridge`; without omnibridge/react,
a request asking `application/json` alone gets the props). A JSON answer carrying a
viewer is private and varies on `Cookie`. The view models (`Base\Screen\*`: Frame, Site,
Viewer, Author, Tag, Link, StoryText, Card, Story, Comment, Reply, Discussion) keep
lesringostars' prop names (`App\Screen\*`, the pilot's); `Base\Screen\Builder\Stories`
makes them from a `Thread` and its comments with the Texts and Pictures contracts and
the clock (`recent`, so that no template compares a moment). All of them are in the
Fondation (`tests/Screen/screens-without-framework.php`).

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
run by `BareTest`). `BaseTrait` itself stays, its accessors unchanged, for the bundles and the
sites that call them.

| Class | Before | Now |
|---|---|---|
| `Thread::__toString()`, `Widget::__toString()` without a title | `getTranslator()->transEntity(static::class)` | `Runtime::nouns()->entity(static::class)` (`NounsTest`) |
| `Thread::getPublishTimeStr()`, `Token::getRemainingTimeStr()`, `getThrottleTimeStr()` | `getTranslator()->transTime($seconds)` | `Duration::spell($seconds, Runtime::translations())` (`DurationTest`: the texts rewritten in the back office printed as before) |
| `Image::__toLink()` | `getMediaService()->getExtension()`, `obfuscate()`, then `getRouter()->generate()` | `Runtime::media()->extension()`, `obfuscate()`, then `Link::to()` (`MediaTest`) |
| `ImageCrop::__toLink()` | `getMediaService()->getExtension()` (after a `getExtensions()` whose answer it overwrote), `generate()` | `Runtime::media()->extension()`, `link()` |

**What an entity is called: `Base\Service\Model\EntityNouns`.** The rule of
`Translator::transEntity()`, `transEntityExists()` and `parseClass()` (the key of a class:
its name less its entity namespace, dotted, in snake case - `Base\Marketplace\Entity\Order`
is `marketplace.order`; then the variants its options ask for, `NounVariants`, the first the
"entities" catalogue holds) moved into a class of the Fondation, built on the Translations
contract and the entity namespaces of the application's bundles. The translator now answers by
an instance of it over itself (`getEntityNouns()`, the bundles `BaseBundle` knows), and that
instance is the Nouns contract under Symfony: one rule, the same strings. A host without the
framework builds one with `new EntityNouns($translations, EntityNouns::namespacesOf([...its
bundle classes]))` and binds it to `Base\Contract\Nouns`.

**A number of seconds in words: `Base\Service\Model\Duration`.** `Translator::transTime()`'s
words (`base.years` to `base.seconds`, ICU plurals; a month of 30 days) over the Translations
contract - omnibridge/twig's, `trans()` alone. Under Symfony that is the `translator` chain, so
a unit the team rewrote in the back office is the one printed, as before; `transTime()` now
answers by it.
