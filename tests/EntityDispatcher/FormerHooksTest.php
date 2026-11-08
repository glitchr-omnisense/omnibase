<?php

namespace Tests\Base\EntityDispatcher;

use Base\Database\Entity\EntityHydratorInterface;
use Base\EntityDispatcher\AbstractEventDispatcher;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Omnibridge\Persistence\Lifecycle;
use Omnibridge\Persistence\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/** On the neutral hooks: what lifecycle() calls. */
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

/** A child still on a former hook, below a parent on the neutral one: the former hook is its own. */
class FormerBelowNeutralDispatcher extends NeutralDispatcher
{
    public function onPersist(LifecycleEventArgs $event)
    {
        $this->called[] = 'onPersist';
    }
}

/** A dispatcher moved to the neutral hook, its former one kept for an older core: the neutral one is given. */
class BothHooksDispatcher extends NeutralDispatcher
{
    public function onPersist(LifecycleEventArgs $event)
    {
        $this->called[] = 'onPersist';
    }

    public function persisting(Lifecycle\PrePersist $event): void
    {
        $this->called[] = 'persisting';
    }
}

/**
 * The entity dispatchers' former hooks, typed on doctrine/persistence's LifecycleEventArgs, and their neutral counterparts
 * (plan "omnibase agnostic", step B, leftover (a)): AbstractEventDispatcher::formerHooks() lists a former hook overridden
 * where its counterpart is not, at the same level or below; Doctrine's entries give such a dispatcher its former hook and
 * any other the neutral one; lifecycle() refuses the former ones aloud.
 */
final class FormerHooksTest extends TestCase
{
    private function dispatcher(string $class): AbstractEventDispatcher
    {
        return new $class($this->createStub(EventDispatcherInterface::class), $this->createStub(EntityHydratorInterface::class), $this->createStub(UnitOfWork::class));
    }

    public function testTheFormerHooksAreTheOnesWithoutTheirCounterpartBelow(): void
    {
        self::assertSame([], AbstractEventDispatcher::formerHooks(NeutralDispatcher::class));
        self::assertSame(['onPersist'], AbstractEventDispatcher::formerHooks(FormerBelowNeutralDispatcher::class));
        self::assertSame([], AbstractEventDispatcher::formerHooks(BothHooksDispatcher::class));
        self::assertSame([], AbstractEventDispatcher::formerHooks(\Base\EntityDispatcher\Event\UserEventDispatcher::class));
        self::assertSame([], AbstractEventDispatcher::formerHooks(\Base\EntityDispatcher\Event\ThreadEventDispatcher::class));
    }

    public function testDoctrinesEntryGivesEachDispatcherItsHook(): void
    {
        $args = new LifecycleEventArgs(new \stdClass(), $this->createStub(ObjectManager::class));
        foreach ([NeutralDispatcher::class => ['persisting'], FormerBelowNeutralDispatcher::class => ['onPersist'], BothHooksDispatcher::class => ['persisting']] as $class => $called) {
            $dispatcher = $this->dispatcher($class);
            $dispatcher->prePersist($args);
            self::assertSame($called, $dispatcher->called, $class);
        }
    }

    public function testTheLifecycleEntryRefusesAFormerHook(): void
    {
        $event = new Lifecycle\PrePersist(new \stdClass(), $this->createStub(UnitOfWork::class));
        $neutral = $this->dispatcher(BothHooksDispatcher::class);
        $neutral->lifecycle($event);
        self::assertSame(['persisting'], $neutral->called);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('overrides onPersist()');
        $this->dispatcher(FormerBelowNeutralDispatcher::class)->lifecycle($event);
    }
}
