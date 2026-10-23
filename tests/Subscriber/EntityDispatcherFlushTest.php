<?php

namespace Tests\Base\Subscriber;

use Base\Database\Entity\EntityHydratorInterface;
use Base\EntityDispatcher\AbstractEventDispatcher;
use Base\Subscriber\EntityDispatcherFlushSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * What the entity dispatchers' listeners changed is flushed when a command
 * ends - by the dispatchers built in the process, which alone can have
 * something pending. The subscriber took every tagged dispatcher, so the end
 * of each command built those not built yet, requiring their files from the
 * container's folder: after bin/console cache:clear, which had just removed
 * it, the command died at console.terminate (lesringostars'
 * CacheLifecycleTest, master plan §5 invariant 3).
 */
final class EntityDispatcherFlushTest extends TestCase
{
    private function dispatcher(EntityManagerInterface $entityManager): AbstractEventDispatcher
    {
        return new class($this->createStub(EventDispatcherInterface::class), $this->createStub(EntityHydratorInterface::class), $entityManager, new RequestStack()) extends AbstractEventDispatcher {
            public function supports(mixed $subject): bool
            {
                return false;
            }

        };
    }

    public function testTheDispatchersBuiltAreFlushedAndNoOtherIsBuilt(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('isOpen')->willReturn(true);
        $entityManager->expects(self::once())->method('flush');

        $pending = $this->dispatcher($entityManager);
        $idle = $this->dispatcher($entityManager);
        // What a listener's change leaves (AbstractEventDispatcher::dispatchEvents()).
        (new \ReflectionProperty(AbstractEventDispatcher::class, 'flushPending'))->setValue($pending, true);

        self::assertContains($pending, AbstractEventDispatcher::live());
        self::assertContains($idle, AbstractEventDispatcher::live());

        // Constructed with nothing: it builds no dispatcher of its own.
        (new EntityDispatcherFlushSubscriber())->flush();
        (new EntityDispatcherFlushSubscriber())->flush();
    }

    public function testADispatcherGoneIsForgotten(): void
    {
        $dispatcher = $this->dispatcher($this->createStub(EntityManagerInterface::class));
        $id = spl_object_id($dispatcher);
        unset($dispatcher);
        gc_collect_cycles();

        self::assertNotContains($id, array_map('spl_object_id', AbstractEventDispatcher::live()));
    }
}
