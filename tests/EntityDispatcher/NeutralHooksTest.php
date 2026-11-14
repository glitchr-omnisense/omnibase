<?php

namespace Tests\Base\EntityDispatcher;

use Base\Database\Entity\EntityHydratorInterface;
use Base\EntityDispatcher\AbstractEventDispatcher;
use Base\EntityDispatcher\EventDispatcherInterface as EntityDispatcherInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Omnibridge\Persistence\Lifecycle;
use Omnibridge\Persistence\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/** On the neutral hooks: what lifecycle() and Doctrine's entries call. */
class NeutralDispatcher extends AbstractEventDispatcher
{
    /** @var list<string> */
    public array $called = [];

    public function supports(mixed $subject): bool
    {
        return true;
    }

    public function persisting(Lifecycle\PrePersist $event): void
    {
        $this->called[] = 'persisting';
    }
}

/**
 * The entity dispatchers' hooks since 6.0 (plan "omnibase agnostic", step B, leftover (a)): the neutral ones only -
 * persisting(), updating(), removing() -, reached from lifecycle() and from Doctrine's entries (prePersist()...); the
 * former ones typed on doctrine/persistence's LifecycleEventArgs (onPersist(), onUpdate(), onRemove(), dispatchEvents())
 * and their list (formerHooks()) are gone.
 */
final class NeutralHooksTest extends TestCase
{
    private function dispatcher(): NeutralDispatcher
    {
        return new NeutralDispatcher($this->createStub(EventDispatcherInterface::class), $this->createStub(EntityHydratorInterface::class), $this->createStub(UnitOfWork::class));
    }

    public function testTheFormerHooksAreGone(): void
    {
        foreach (['onPersist', 'onUpdate', 'onRemove', 'dispatchEvents', 'formerHooks', 'getEntityManager'] as $method) {
            self::assertFalse(method_exists(AbstractEventDispatcher::class, $method), $method);
            self::assertFalse(method_exists(EntityDispatcherInterface::class, $method), $method);
        }
        self::assertFalse(\defined(AbstractEventDispatcher::class.'::FORMER_HOOKS'));
    }

    public function testBothEntriesReachTheNeutralHook(): void
    {
        $dispatcher = $this->dispatcher();
        $dispatcher->prePersist(new LifecycleEventArgs(new \stdClass(), $this->createStub(ObjectManager::class)));
        $dispatcher->lifecycle(new Lifecycle\PrePersist(new \stdClass(), $this->createStub(UnitOfWork::class)));
        self::assertSame(['persisting', 'persisting'], $dispatcher->called);
    }
}
