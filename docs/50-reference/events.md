---
title: Events
order: 30
---

# Events

The events the core dispatches - through Symfony's event dispatcher, and through
Doctrine's event manager for those of the mapping and the queries - and the
listeners it registers on the framework's own events. The lists of listeners
are `bin/console debug:event-dispatcher` and `doctrine:debug:event-manager` in
the core's harness, the core's alone (the plugins' listeners left out).

## Events the core dispatches

| Event name | Event class | Dispatched by | When |
|---|---|---|---|
| `thread.scheduled` | `Base\EntityDispatcher\Event\ThreadEvent` | `ThreadEventDispatcher` | A thread is saved scheduled (its publication time in the future) |
| `thread.publishable` | `ThreadEvent` | `ThreadEventDispatcher` | A thread is saved and its publication time has come |
| `thread.published` | `ThreadEvent` | `ThreadEventDispatcher` | A thread is saved published |
| `user.register` | `Base\EntityDispatcher\Event\UserEvent` | `UserEventDispatcher` | A user is persisted |
| `user.approval` | `UserEvent` | `UserEventDispatcher` | A user becomes approved |
| `user.verified` | `UserEvent` | `UserEventDispatcher` | A user becomes verified |
| `user.enabled` | `UserEvent` | `UserEventDispatcher` | A user becomes enabled |
| `user.disabled` | `UserEvent` | `UserEventDispatcher` | A user becomes disabled |
| `user.kickout` | `UserEvent` | `UserEventDispatcher` | A user becomes kicked out |
| `user.locked` | `UserEvent` | `UserEventDispatcher` | A user becomes locked |
| `user.banned` | `UserEvent` | `UserEventDispatcher` | A user becomes banned |
| `user.ghost` | `UserEvent` | `UserEventDispatcher` | A user stops being a ghost (`isGhost()` was true, is false) |
| `sitemap.build` | `Base\Event\SitemapEvent` | `SitemapController` (`/sitemap.{extension}`) | Once the routes carrying a `Sitemap` attribute are registered |
| `base.signature.signed` | `Base\Event\SignatureEvent` | `Base\Service\Signatures` | A signer of an envelope signed (the gateway's answer applied) |
| `base.signature.completed`, `.declined`, `.expired`, `.canceled` | `SignatureEvent` | `Base\Service\Signatures` | An envelope reached that state |

`UserEvent::NEWCOMER` (`user.newcomer`) is declared and dispatched by nothing.
The constants are the names to listen to: `ThreadEvent::PUBLISHABLE`,
`UserEvent::APPROVAL`, `SitemapEvent::BUILD`, `SignatureEvent::COMPLETED`...

### Entity events

`ThreadEvent` and `UserEvent` extend `Base\EntityDispatcher\AbstractEvent`:

| Method | Returns |
|---|---|
| `getObject()` | the entity (`getThread()`, `getUser()` typed) |
| `getObjectClass()` | its class |
| `getLifecycle()` | Doctrine's `LifecycleEventArgs` the event comes from |
| `getObjectManager()` | the entity manager |
| `getRequest()` | the current request, or `null` (a command, a worker) |

Their dispatchers (`Base\EntityDispatcher\AbstractEventDispatcher`) are Doctrine
listeners: on `prePersist` and `preUpdate` they compare the entity with its
state before the change (`EntityHydrator::getOriginalEntity()`) and record the
events it raises; on `postPersist`, `postUpdate` and `postRemove` they dispatch
them, each event once per entity. A listener may change entities, but never
flushes: the dispatcher flushes those changes once the flush that raised the
events is over, from `EntityDispatcherFlushSubscriber` (on `kernel.response`,
`console.terminate` and `WorkerMessageHandledEvent`, priority 64), for five
rounds at most. A flush the application makes later in the request takes them
along.

An application adds its own: a class extending `AbstractEventDispatcher`, named
after its event class with `Dispatcher` appended (`App\EntityDispatcher\Event\OrderEventDispatcher`
dispatches `App\EntityDispatcher\Event\OrderEvent`, which extends `AbstractEvent`),
with `supports()`, `onPersist()`, `onUpdate()` or `onRemove()` calling
`addEvent($name, $entity)`. Autoconfiguration tags it (see
[What the extension registers](configuration.md#what-the-extension-registers)).

```php
namespace App\EntityDispatcher\Event;

use App\Entity\Order;
use Base\EntityDispatcher\AbstractEventDispatcher;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class OrderEventDispatcher extends AbstractEventDispatcher
{
    public function supports(mixed $subject): bool
    {
        return $subject instanceof Order;
    }

    public function onUpdate(LifecycleEventArgs $event)
    {
        $order = $event->getObject();
        if ($order->isPaid() && !$this->entityHydrator->getOriginalEntity($event)->isPaid()) {
            $this->addEvent(OrderEvent::PAID, $order);
        }
    }
}
```

### What the core does on them

`Base\EntitySubscriber\ThreadSubscriber` listens to `thread.scheduled` (the
thread's state becomes `FUTURE`) and `thread.publishable` (its state becomes
`PUBLISH`; each author is e-mailed `thread.published`, each member mentioned who
is not an author `thread.mentioned`).

> [!WARNING]
> Known defect: `Base\EntitySubscriber\UserSubscriber` - the welcome back on `user.enabled`, the
> goodbye on `user.disabled`, the verification e-mail on `user.register`, the approval's
> confirmation on `user.approval` - is registered by no configuration since the back office
> left the core (commit 80088b2d removed `config/services/admin.php`, which declared it): the
> `user.*` events are dispatched with no listener of the core. `SecurityController` sends the
> e-mails of its own pages (registration, verification) itself.

### `SitemapEvent`

`registerAttributes()` can only list the routes it can generate without
parameters; the pages of entities are the application's to list. `getSitemapper()`
gives the `SitemapperInterface` being built, `getHostname()` the scheme and host
of the request. See [Sitemap](../40-commons/sitemap.md).

### `SignatureEvent`

`getEnvelope()` the `Envelope`, `getSubject()` the entity it is about when it
still exists, `getSigner()` the signer's key (`SIGNED` only). See
[Signatures](../40-commons/signatures.md).

## Doctrine events of the core

`Base\Database\Events` names four events dispatched on the entity manager's
event manager:

| Event | Arguments | Dispatched by | When |
|---|---|---|---|
| `resolveDiscriminator` | `Base\Database\Event\ResolveDiscriminatorEventArgs` (a `LoadClassMetadataEventArgs`) | `Base\Bridge\Doctrine\MappingDriver` | While a class's metadata is loaded, to complete its discriminator map |
| `preQuery` | `Base\Database\Event\DoctrineQueryEventArgs` (`getQueryBuilder()`, `setQueryBuilder()`) | `ServiceEntityParser::getQuery()` (omnibase's repositories' magic finders) | Before the query is built: a listener may replace the query builder |
| `onQuery` | `DoctrineQueryEventArgs` (`getQuery()`, `setQuery()`) | the same | Once the query is built |
| `postQuery` | `DoctrineQueryEventArgs` (`getQuery()`) | the same | After the cache settings are applied |

`DoctrineQueryEventArgs::getClassMetadata()` and `getEntityName()` say which
entity is queried. Each is dispatched only when a listener exists.

## What the core listens to

### The kernel

| Event | Listener | Priority |
|---|---|---|
| `kernel.request` | `RequestScopedStateSubscriber::onKernelRequest` | 4096 |
| | `ProfilerSubscriber::onKernelRequest` | 512 |
| | `HotParameterBagSubscriber::onRequest` | 512 |
| | `ValidCacheSubscriber::onValidCache` | 128 |
| | `TwigSubscriber::onKernelRequest` | 8 |
| | `AnalyticsSubscriber::onUserRequest` | 8 |
| | `LocalizerSubscriber::onKernelRequest` | 8 |
| | `RouterSubscriber::onKernelRequest` | 7 |
| | `AnalyticsSubscriber::onGoogleAnalyticsRequest` | 7 |
| | `SecuritySubscriber::onAccessRequest` | 6 |
| | `PasswordChangeSubscriber::onKernelRequest` | 5 |
| | `SecurityEnrolmentSubscriber::onKernelRequest` | 4 |
| | `SecuritySubscriber::onMaintenanceRequest` | 4 |
| | `SecuritySubscriber::onLaunchRequest` | 4 |
| | `ReferrerSubscriber::onKernelRequest` | 4 |
| | `SecuritySubscriber::onKernelRequest` | 3 |
| | `NotifierSubscriber::onKernelRequest` | 0 |
| `kernel.controller_arguments` | `IsGrantedSubscriber::onKernelControllerArguments` | 0 |
| `kernel.view` | `Base\Bridge\Symfony\ActionResults::__invoke` (an action's `View`, `Template`, `Json`... answered by the `ScreenResponder`) | 0 |
| `kernel.response` | `EntityDispatcherFlushSubscriber::onResponse` | 64 |
| | `ProfilerSubscriber::onKernelResponse` | 0 |
| | `TwigSubscriber::onKernelResponse` | 0 |
| | `SecuritySubscriber::onKernelResponse` | 0 |
| | `ReferrerSubscriber::onKernelResponse` | 0 |
| | `LocalizerSubscriber::onKernelResponse` | 0 |
| | `FlashBagSubscriber::onKernelResponse` | -10 |
| `kernel.exception` | `RedirectionSubscriber::onKernelException` (the redirections of the back office, see [Redirections](../40-commons/redirections.md)) | 16 |
| | `TwigSubscriber::onKernelException` | 0 |
| `kernel.terminate` | `PageViewSubscriber::onKernelTerminate` (the page views of `Base\Service\Analytics`) | 0 |

The `Subscriber` classes are in `Base\Subscriber\`. In the `demo` environment, and
only there, `config/services/demo.php` registers `DemoSubscriber` (`kernel.response`
at -1024: `noindex` on every response; `MessageEvent` at 1024: no e-mail leaves),
`DemoBackOfficeSubscriber` (`CheckPassportEvent` at 128, `kernel.request` at 4)
and the Doctrine listener `DemoAccountLockSubscriber` (`preFlush`): see
[The demo environment](../20-architecture/demo.md). `IntegritySubscriber`, in the
same directory, is registered by no configuration.

### The console, the mailer and Messenger

| Event | Listener | Priority |
|---|---|---|
| `console.command` | `RouterSubscriber::onCommand` | 2048 |
| | `HotParameterBagSubscriber::onRequest` | 2048 |
| `console.terminate` | `EntityDispatcherFlushSubscriber::flush` | 64 |
| | `SecondLevelCacheConsoleSubscriber::onTerminate` | -64 |
| `Symfony\Component\Mailer\Event\MessageEvent` | `TemplatedEmailSubjectSubscriber::onMessage` | 16 |
| | `MailTrapSubscriber::onMessage` (see `base:mailer:trap` in [Console commands](commands.md#basemailertrap)) | -1000 |
| `Symfony\Component\Messenger\Event\WorkerMessageHandledEvent` | `EntityDispatcherFlushSubscriber::flush` | 64 |

### Security and omniauth

| Event | Listener | Priority |
|---|---|---|
| `CheckPassportEvent` | `Base\Security\SignInGuard::onCheckPassport` (the captcha after failed sign-ins, see [The forms' guard](../20-architecture/guard.md#the-sign-in)) | 300 |
| | `Base\EntitySubscriber\ConnectionSubscriber::onCheckPassport` | 10 |
| `LoginSuccessEvent` | `SignInGuard::onLoginSuccess` | 0 |
| | `NewDeviceSubscriber::onLoginSuccess` | 0 |
| | `ConnectionSubscriber::onLoginSuccess` | -65 |
| | `SecuritySubscriber::onLoginSuccess` | -65 |
| `LoginFailureEvent` | `SignInGuard::onLoginFailure`, `ConnectionSubscriber::onLoginFailure`, `SecuritySubscriber::onLoginFailure` | 0 |
| `LogoutEvent` | `ConnectionSubscriber::onLogout`, `SecuritySubscriber::onLogout` | 0 |
| `security.switch_user` | `LocalizerSubscriber::onSwitchUser` | 0 |
| `Omniauth\Event\SignedIn` | `Base\Auth\Listener\TrackConnections::onSignedIn` | 16 |
| | `Base\Auth\Listener\WarnNewDevice::onSignedIn` | 8 |
| | `Base\Auth\Listener\UpdateAccount::onSignedIn`, `Base\Auth\Listener\GuardSignIn::onSignedIn` | 0 |
| `Omniauth\Event\SignInFailed` | `TrackConnections::onSignInFailed`, `GuardSignIn::onSignInFailed` | 0 |
| `Omniauth\Event\Verifying` | `GuardSignIn::onVerifying` | 0 |
| `Omniauth\Event\AccountRefused` | `Base\Auth\Listener\WelcomeBack::onAccountRefused` | 0 |

The `Omniauth\Event\*` listeners exist where omnisense/omniauth is installed (see
[Account security](../20-architecture/account-security.md)).

### Doctrine

| Event | Listeners, in order |
|---|---|
| `loadClassMetadata` | `AttributeSubscriber`, `IntlSubscriber`, (Doctrine's `AttachEntityListenersListener`), `TrackingPolicySubscriber`, `ExtensionSubscriber` |
| `resolveDiscriminator` | `AttributeSubscriber`, `IntlSubscriber` |
| `onFlush` | `AttributeSubscriber`, `IntlSubscriber`, `TrasheableSubscriber`, `VersionableSubscriber`, `ExtensionSubscriber`, `InverseCollectionCacheSubscriber` |
| `preFlush` | `AttributeSubscriber`, `IntlSubscriber` |
| `postFlush` | `AttributeSubscriber`, `TrasheableSubscriber`, `VersionableSubscriber`, `Base\Translation\TextOverrideCacheListener`, `ThreadEventDispatcher`, `UserEventDispatcher` |
| `prePersist` | `AttributeSubscriber`, `IntlSubscriber`, `ThreadEventDispatcher`, `UserEventDispatcher` |
| `postPersist` | `AttributeSubscriber`, `TextOverrideCacheListener`, `SettingSubscriber`, `ThreadEventDispatcher`, `UserEventDispatcher`, `ExtensionSubscriber` |
| `preUpdate` | `AttributeSubscriber`, `ThreadEventDispatcher`, `UserEventDispatcher` |
| `postUpdate` | `AttributeSubscriber`, `TextOverrideCacheListener`, `SettingSubscriber`, `ThreadEventDispatcher`, `UserEventDispatcher` |
| `preRemove` | `AttributeSubscriber`, `ThreadEventDispatcher`, `UserEventDispatcher` |
| `postRemove` | `AttributeSubscriber`, `TextOverrideCacheListener`, `SettingSubscriber`, `ThreadEventDispatcher`, `UserEventDispatcher` |
| `postLoad` | `AttributeSubscriber`, `IntlSubscriber` |
| `preQuery`, `postQuery` | `AttributeSubscriber` |
| `onQuery` | `AttributeSubscriber`, `IntlSubscriber` |

`AttributeSubscriber`, `IntlSubscriber`, `TrackingPolicySubscriber` and
`InverseCollectionCacheSubscriber` are `Base\DatabaseSubscriber\`;
`TrasheableSubscriber`, `VersionableSubscriber`, `ExtensionSubscriber`,
`SettingSubscriber` are `Base\EntitySubscriber\`. Outside Symfony,
`Base\Bridge\Doctrine\Mapping` adds the same listeners to an entity manager built
by hand (see [The Doctrine mapping](../20-architecture/doctrine.md)).
