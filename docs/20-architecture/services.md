---
title: The services, everywhere
order: 31
---

# The services, everywhere

Marco's answer 3 to the plan "omnibase agnostic" (2026-11-01): the host-only Symfony code leaves for
omnibridge/symfony, **but the services stay available everywhere** - under Symfony, under Laravel, and
in a plain PHP script. Step F5a (2026-11-02) is what makes that true.

## One list, three containers

`Base\Services::definitions()` is the core's services as data (every one of them since step F5e slice 4a: 192
definitions in `src/Resources/services/<group>.php`): each a class (or its parent's), its service id (the class by
default), the contracts it answers (interfaces and former ids, aliased to it, with their visibility), its arguments by
position or by name in `Omnibridge\Container`'s syntax - `'@id'` a service, `'@?id'` one or null, `'%name%'` a
parameter, `['!tagged_iterator' => tag]`, `['!service_closure' => '@id']` -, its bindings, calls, tags, decoration and
factory, what a host must have installed for it (`requires`) and the environment it is for (`environment`); what only
Symfony's container gives it, by Symfony's own ids, is its `symfony` part. The format is omnisense/omnibridge's
`Omnibridge\Container\Declarations` (its docs/container.md).

| Host | Reads the list with |
|---|---|
| Symfony | omnibridge/symfony's `Omnibase\DependencyInjection\Declarations::group()`, called by its `config/omnibase/services*.php` for each group in its place among the bridge's own definitions (the first group in `services-list.php`, loaded first): the compiled container is the one the former Symfony files built, the order of the services a tag collects included. `tests/ServicesTest` checks that the container answers every public service and contract. |
| Laravel | omnibridge/laravel's `Container\Definitions::bind()`, at boot: every declared service that applies (its `requires`, its environment) and can be built there (what its constructor needs is bound or declared, its options configured) and that it (or the application) does not bind yet is a singleton of its class, its contracts aliases, its tags Laravel's; a decoration is left to the host's own wiring. |
| Plain PHP | omnisense/omnibridge's `Omnibridge\Container::fromDefinitions()` (docs: omnibridge's `docs/container.md`). |

The whole graph builds in plain PHP: `tests/Services/graph-without-framework.php` (BareTest, on the core installed
alone) gives `Omnibridge\Container` the host's contracts of `container-without-framework.php`, stand-ins for the
repositories and the request's contracts, and builds every definition that applies (91 there; the others say what they
require: Twig, omniauth, symfony/validator, the Symfony router...).

What a constructor asks and the list does not give is the host's: omnibridge's contracts (`Urls`,
`Views`, `Environment`, `Translations`, `Locales`...), `Base\Service\ParameterBagInterface`,
`Base\Contract\MediaRouter`, `Base\Service\Storages`, and three ids - `imagine.bitmap`, `imagine.svg`
and `Symfony\Component\Translation\Translator` (the catalogues the core's translator decorates).

```php
$container = Omnibridge\Container::fromDefinitions(Base\Services::definitions(), $parameters, [
    Base\Service\ParameterBagInterface::class => new Base\Service\ArrayParameterBag($parameters),
    Omnibridge\Contract\Views::class => $views,
    Omnibridge\Contract\Urls::class => $urls,
    Omnibridge\Contract\Environment::class => $environment,
    Base\Contract\MediaRouter::class => $mediaRouter,
    Base\Service\Storages::class => new Base\Service\Flysystem($drives),
    'imagine.bitmap' => new Imagine\Imagick\Imagine(),
    'imagine.svg' => new Base\Imagine\Svg\Imagine(),
    Symfony\Component\Translation\Translator::class => $catalogues,
]);
$container->get(Base\Notifier\Mail\EmailComposer::class)->compose('anne@example.org', '@App/mail/welcome.html.twig', ['name' => 'Anne']);
```

`tests/Services/container-without-framework.php` is such a script, run by `bare --strict`
(BareTest::testTheServicesAreBuiltByAPlainPhpContainer): nothing of Symfony's container, the framework,
Doctrine nor Twig is loaded.

## Rendering through Views

The services that rendered with Twig's environment render through omnibridge's `Views` (and, for a
picture an e-mail embeds, `ViewSources`): the notifier (`BaseNotifier::getViews()`), `EmailComposer`
and `InlineImages`, `Notification::renderHtml()`, `MediaService`/`FileService`'s media tags,
`Sitemapper`, `QrSheet`, the sharing adapters, `WysiwygEnhancer`/`EditorEnhancer`, the
`ScreenResponder`. Under Symfony and Laravel `Views` is omnibridge/twig's `TwigViews` over the host's
environment (`config/services/twig.php`, omnibridge/laravel's provider); an application binds its own
to render them with another engine. The e-mail's text part is written in PHP (`EmailComposer::text()`,
what Twig wrote from `EmailComposer::TEXT`).

Each of these constructors still accepts Twig's environment, deprecated until 6.0
(`Base\View\ViewsAdapter` wraps it in a `TwigViews`, with an `E_USER_DEPRECATED`).
`BaseNotifier::getEnvironment()` is deprecated for `getViews()`; `BaseNotifierInterface` declares
`getViews()` in its place.

## On contracts

- `Paginator` and `Pagination`: their links through `Urls` (`Urls|RouterInterface`; Symfony's
  `advanced_router` under Symfony, the links as they were).
- `Translator`: whether the kernel debugs through omnibridge's `Environment` (`Environment|KernelInterface`).
- `Obfuscator`: its compressions may be given to its constructor (`iterable $compressions`).
- `AbstractEventDispatcher` and `BaseNotifier`: omnibridge's `UnitOfWork` (the entity manager accepted,
  deprecated, as step F2d did; `getEntityManager()` deprecated for `getUnitOfWork()`); the dispatcher's
  association change set reads the entity's shape.
- `DemoAccountLockSubscriber`: its rule is `Base\Demo\DemoAccountLock`'s (the original row, the columns,
  how to read and write them); the listener keeps Doctrine's identity map (it leaves with omnibridge/doctrine);
  the request and the flash message go through `Omnibridge\Contract\Requests` (F5c).

### The request, the session and the events (F5c)

omnisense/omnibridge's `Omnibridge\Contract\Requests` is the request being answered, read without the
framework's request object: `has()`, `ip()`, `header()`, `uri()`, `host()`, `route()`, `attribute()`, the
session's `session()`, `remember()`, `forget()`, its flashes `flash()`, `flashes()`, and `request()` for code
that still needs the host's own object. Symfony answers it with omnibridge/symfony's `StackRequests`,
Laravel with omnibridge/laravel's `LaravelRequests`; outside a request nothing is read or written.

| Service | Reads now | Former argument (deprecated until 6.0) |
|---|---|---|
| `AbstractEventDispatcher` (user.*, thread.*, an application's) | `Requests` (the event's request), any PSR-14 dispatcher | `RequestStack`, Symfony's dispatcher contract |
| `SpamChecker` | `Requests` (address, browser, referrer, page), the home page through the router or `Urls` | `RequestStack` |
| `Referrer` | `Requests` (the target paths kept in the session, the referer) | `RequestStack` |
| `BrowserPlusChannel` (`browser+`) | `Requests` (the flashes) | `RequestStack` |
| `DemoAccountLockSubscriber` | `Requests` (whether a request is answered, the warning) | `RequestStack` |
| `UserSubscriber` | `Access` (who is signed in) | Security's token storage |
| `Launcher`, `MaintenanceProvider` | `Access` (roles, who is signed in) | Security's checker and token storage |
| `BaseNotifier`, `Notifier` | omnibase's router or `Urls` (the links), `Requests` (the route) | - |
| `Localizer` | omnibase's `ParameterBagInterface` or the container's | - |

A `RequestStack` given is wrapped by `Base\Http\RequestsOf` in omnibridge/symfony's `StackRequests`.

The entity events are dispatched with `Omnibridge\Event\Events::dispatch()` through any PSR-14
dispatcher. `AbstractEvent` is a `NamedEvent`: its dispatcher sets the name it is raised under
(`user.register`), Symfony's dispatcher is given that name, omnibridge/laravel's `DispatcherEvents` gives
the event to the listeners of its name, so `UserSubscriber::listen()` registers the same listeners on
Laravel's dispatcher as `getSubscribedEvents()` does on Symfony's.

A notification's e-mail is the Twig bridge's `NotificationEmail` under Symfony; on a host without Symfony's
Twig bridge (Laravel) it is `EmailComposer::compose()`'s, from `NotificationMessages::composed()`.

## Left for later steps

- F5b: the field and form types (the neutral form layer); the services that take Symfony's form factory.
- F5c, what its first part left: `BaseService` (the facade, Symfony's by nature), `HotParameterBag` (the container's
  half of `HotParameters`), `SecurityPolicy` (the role hierarchy), the field types' `twig` arguments, `EntityHydrator`
  (Doctrine's metadata), `Referrer`'s and the Notifier's use of omnibase's router; the moves of §2.2 (Console, the
  kernel subscribers, Routing, the warmers, Security, the controllers, DI, BaseBundle); config/services/*.php,
  which then read the list.
