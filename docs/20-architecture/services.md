---
title: The services, everywhere
order: 31
---

# The services, everywhere

Marco's answer 3 to the plan "omnibase agnostic" (2026-11-01): the host-only Symfony code leaves for
omnibridge/symfony, **but the services stay available everywhere** - under Symfony, under Laravel, and
in a plain PHP script. Step F5a (2026-11-02) is what makes that true.

## One list, three containers

`Base\Services::definitions()` is the core's services as data: each a class, its service id (the class
by default), the contracts it answers (interfaces and former ids, aliased to it) and its arguments by
name, in `Omnibridge\Container`'s syntax - `'@id'` a service, `'%name%'` a parameter.

| Host | Reads the list with |
|---|---|
| Symfony | `config/services-list.php`, loaded last by `BaseExtension`: each listed contract the configuration does not alias itself is aliased to its service. The services themselves are still wired by `config/services/*.php`, which move to omnibridge/symfony at F5c, where it reads the list in their place. `tests/ServicesTest` checks that the two agree. |
| Laravel | omnibridge/laravel's `Container\Definitions::bind()`, at boot: every listed service it (or the application) does not bind yet is a singleton of its class, its contracts aliases. |
| Plain PHP | omnisense/omnibridge's `Omnibridge\Container::fromDefinitions()` (docs: omnibridge's `docs/container.md`). |

The list today: the obfuscator and its five compressions, `MediaService`, `Paginator`, `Translator`,
`Stories`, `EmailComposer`. It grows as each service is written on contracts.

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
  how to read and write them); the listener keeps Doctrine's identity map, the request and the flash
  message, which leave with omnibridge/doctrine and omnibridge/symfony.

## Left for later steps

- F5b: the field and form types (the neutral form layer); the services that take Symfony's form factory.
- F5c: the Symfony host's code, and with it the services that still read the request, the session, the
  firewall or the kernel (`AbstractEventDispatcher`'s `RequestStack`, `BaseService`, `Referrer`,
  `Launcher`, `MaintenanceProvider`, `Localizer`, `HotParameterBag`, `SecurityPolicy`,
  `TemplatedEmailSubjectSubscriber`, `AnalyticsSubscriber`...); config/services/*.php, which then read the list.
