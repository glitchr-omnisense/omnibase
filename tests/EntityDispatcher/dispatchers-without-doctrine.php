<?php

/*
 * The core's entity dispatchers where doctrine/persistence is not installed (plan "omnibase agnostic", step B, leftover
 * (a)): run by docker/bin/bare --strict on the core installed alone for tests/BareTest.php. A persistence other than
 * Doctrine's calls AbstractEventDispatcher::lifecycle() with omnisense/omnibridge's Lifecycle events: UserEventDispatcher
 * and ThreadEventDispatcher note their events in persisting() / updating() and dispatch them after the write, through a
 * plain PSR-14 dispatcher; the former hooks typed on doctrine/persistence are gone (6.0).
 */

namespace {
    use Base\Database\Entity\EntityHydrator;
    use Base\Database\Entity\EntityHydratorInterface;
    use Base\Entity\Thread;
    use Base\Service\Model\ClassAliases;
    use Base\Enum\ThreadState;
    use Base\EntityDispatcher\AbstractEvent;
    use Base\EntityDispatcher\AbstractEventDispatcher;
    use Base\EntityDispatcher\Event\ThreadEventDispatcher;
    use Base\EntityDispatcher\Event\UserEventDispatcher;
    use Omnibridge\Contract\Locales;
    use Omnibridge\Persistence\Lifecycle;
    use Omnibridge\Persistence\UnitOfWork;
    use Omnibridge\Runtime;
    use Psr\EventDispatcher\EventDispatcherInterface;

    // The Base\ / App\ convention as the core resolves it without a kernel: App\Entity\User, which UserEventDispatcher
    // supports, is the core's account where the application has none (its states, App\Enum\UserState...).
    ClassAliases::register();
    // A thread's title is translatable: the page's language from the contract.
    Runtime::set(Locales::class, new class implements Locales {
        public function getLocale(): string
        {
            return 'fr-FR';
        }

        public function getDefaultLocale(): string
        {
            return 'fr-FR';
        }

        public function getAvailableLocales(): array
        {
            return ['fr-FR'];
        }
    });

    $heard = new \ArrayObject();
    $events = new class($heard) implements EventDispatcherInterface {
        public function __construct(private \ArrayObject $heard)
        {
        }

        public function dispatch(object $event): object
        {
            $this->heard[] = $event;

            return $event;
        }
    };
    $unitOfWork = new class implements UnitOfWork {
        public int $flushes = 0;

        public function persist(object $entity): void
        {
        }

        public function remove(object $entity): void
        {
        }

        public function flush(): void
        {
            ++$this->flushes;
        }

        public function refresh(object $entity): void
        {
        }

        public function contains(object $entity): bool
        {
            return true;
        }

        public function reference(string $class, mixed $id): object
        {
            return new $class();
        }

        public function transactional(callable $work): mixed
        {
            return $work($this);
        }

        public function isNew(object $entity): bool
        {
            return false;
        }

        public function identifier(object $entity): mixed
        {
            return null;
        }

        public function className(object $entity): string
        {
            return $entity::class;
        }

        public function changes(object $entity): array
        {
            return [];
        }

        public function recompute(object $entity): void
        {
        }
    };
    /** The entity as it was before the update: what EntityHydrator reads from the unit of work and a PreUpdate's changes. */
    $originals = new \SplObjectStorage();
    $hydrator = new class($originals) implements EntityHydratorInterface {
        public function __construct(private \SplObjectStorage $originals)
        {
        }

        public function hydrate(mixed $entity, null|array|object $data = [], array $fieldExceptions = [], int $aggregateModel = EntityHydrator::DEFAULT_AGGREGATE, ...$constructArguments): mixed
        {
            return $entity;
        }

        public function dehydrate(mixed $entity, array $fieldExceptions = []): ?array
        {
            return [];
        }

        public function getOriginalEntity($eventOrEntity)
        {
            return $this->originals[$eventOrEntity instanceof Lifecycle\EntityEvent ? $eventOrEntity->entity : $eventOrEntity];
        }
    };
    $names = static fn (): array => array_map(static fn (object $event): string => $event instanceof AbstractEvent ? $event->eventName() : $event::class, $heard->getArrayCopy());

    // An account written: user.register once inserted; verified by an update: user.verified once updated.
    $users = new UserEventDispatcher($events, $hydrator, $unitOfWork);
    $user = new App\Entity\User('anne@example.test');
    $users->lifecycle(new Lifecycle\PrePersist($user, $unitOfWork));
    $beforeInsert = $names();
    $users->lifecycle(new Lifecycle\PostPersist($user, $unitOfWork));
    $registered = $names();
    $register = $heard[0] ?? null;
    $originals[$user] = clone $user;
    $user->setIsVerified(true);
    $users->lifecycle(new Lifecycle\PreUpdate($user, $unitOfWork, ['states' => [$originals[$user]->getStates(), $user->getStates()]]));
    $users->lifecycle(new Lifecycle\PostUpdate($user, $unitOfWork));
    $verified = array_slice($names(), \count($registered));
    $users->flushPending();

    // A thread published when it is inserted: thread.published.
    $heard->exchangeArray([]);
    $threads = new ThreadEventDispatcher($events, $hydrator, $unitOfWork);
    $thread = new Thread();
    $thread->setState(ThreadState::PUBLISH);
    $threads->lifecycle(new Lifecycle\PrePersist($thread, $unitOfWork));
    $threads->lifecycle(new Lifecycle\PostPersist($thread, $unitOfWork));
    // A user is no thread: the thread dispatcher notes nothing for it.
    $threads->lifecycle(new Lifecycle\PrePersist($user, $unitOfWork));
    $threads->lifecycle(new Lifecycle\PostPersist($user, $unitOfWork));

    return [
        'doctrine/persistence' => class_exists('Doctrine\\Persistence\\Event\\LifecycleEventArgs'),
        'former hooks gone' => !method_exists(AbstractEventDispatcher::class, 'onPersist') && !method_exists(AbstractEventDispatcher::class, 'formerHooks'),
        'user' => [$beforeInsert, $registered, $verified],
        'user.register carries' => [$register?->getObject() === $user, $register?->getUnitOfWork() === $unitOfWork, $register?->getLifecycle() instanceof Lifecycle\PostPersist],
        'flushed after the events' => $unitOfWork->flushes,
        'thread' => $names(),
    ];
}
