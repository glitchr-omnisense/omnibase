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
| `Base\Contract\Urls` (extends omnibridge/twig's `Urls`) | `path()` and `url()` of a route, `asset()` of a public file | `Base\Bridge\Symfony\RouterUrls`: `advanced_router`, the asset packages - Twig's `path()`, `url()`, `asset()` |
| `Omnibridge\Twig\Contract\Translations` | `trans()`, ICU messages | `Base\Bridge\Symfony\TranslatorTranslations`: the `translator` chain (texts rewritten in the back office, `Base\Service\Translator`, Symfony's) - Twig's `\|trans` |
| `Base\Contract\Access` | `isGranted()` an attribute, on a subject (a `Thread`...), `getUser()` | `Base\Bridge\Symfony\SecurityAccess`: the authorization checker and the token storage - `BaseService::isGranted()`, Twig's `is_granted()` |
| `Base\Contract\Locales` | the current locale, the default, the available ones (`fr-FR`) | `Base\Bridge\Symfony\LocalizerLocales`: the `localizer` |
| `Base\Contract\Files` | a stored file's public path and web address, its local path, whether its storage is remote | `Base\Bridge\Symfony\FlysystemFiles`: `flysystem`, and the image route for a remote storage, as the uploader resolves it |
| `Psr\Clock\ClockInterface` (PSR-20) | `now()` | the framework's `clock` (symfony/clock); the machine's (`Base\Contract\SystemClock`) when there is none |
| `Psr\EventDispatcher\EventDispatcherInterface` (PSR-14) | `dispatch()` | `event_dispatcher` |
| `Symfony\Component\Mailer\MailerInterface` | `send()` | `mailer.mailer` |

`Locales` is plural, as omnibridge's `Urls` and `Translations` are, and so that
importing it never shadows PHP's `\Locale`.

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
