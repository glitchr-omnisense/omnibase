<?php

namespace Tests\Base\Database\Entity\Extension;

use Base\Entity\Extension\TrashBall;
use Base\Entity\Thread;
use Omnibridge\Doctrine\Omnibase\EntitySubscriber\TrasheableSubscriber;
use Base\Database\UnitOfWorks;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Base\Database\Backend\BothBackends;

/**
 * How long a trashed entity is kept (base.extension.empty_trash) reads as a
 * relative date: "+7 days" by default - what TrasheableSubscriber and the
 * purge command say -, a bare number as a number of days. The default was 5,
 * which new DateTime() refused, so on a host without its own value removing a
 * Thread threw (DateMalformedStringException) instead of putting it in the bin.
 */
final class EmptyTrashTest extends KernelTestCase
{
    use BothBackends;

    public function testAnExpiryReadsAsARelativeDate(): void
    {
        self::assertSame('+7 days', TrasheableSubscriber::expiry(null));
        self::assertSame('+7 days', TrasheableSubscriber::expiry(''));
        self::assertSame('+5 days', TrasheableSubscriber::expiry(5));
        self::assertSame('+5 days', TrasheableSubscriber::expiry('5'));
        self::assertSame('+1 month', TrasheableSubscriber::expiry('+1 month'));
        self::assertSame('+7 days', TrasheableSubscriber::expiry(' +7 days '));

        $subscriber = new class(5) extends TrasheableSubscriber {
            public function emptyTrash(): string
            {
                return $this->emptyTrash;
            }
        };
        self::assertSame('+5 days', $subscriber->emptyTrash());
    }

    /** On both persistences (BothBackends, plan "omnibase agnostic", step F7c): the trash entry is read through the backend's repositories.
     *
     * @dataProvider backends
     */
    public function testRemovingAThreadPutsItInTheBinForTheDefaultTime(string $backend): void
    {
        if (!class_exists('App\\Kernel') || !class_exists('App\\Entity\\User')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
        $container = static::getContainer();
        self::assertSame('+7 days', $container->getParameter('base.extension.empty_trash'), 'the harness sets no value of its own: the default');

        $em = $this->unitOfWork($backend);
        $thread = new Thread(null, null, 'Un fil à jeter', 'un-fil-a-jeter-'.bin2hex(random_bytes(3)));
        $em->persist($thread);
        $em->flush();
        $id = $thread->getId();

        $before = new \DateTimeImmutable('+7 days -1 minute');
        $em->remove($thread);
        $em->flush();
        $after = new \DateTimeImmutable('+7 days +1 minute');

        $ball = UnitOfWorks::repositories($em)->of(TrashBall::class)->findOneBy(['entityClass' => Thread::class, 'entityId' => $id], ['id' => 'DESC']);
        self::assertNotNull($ball, 'the thread is in the bin');
        $permanent = \DateTimeImmutable::createFromInterface($ball->getPermanentAfter());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $permanent->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $permanent->getTimestamp());

        // Removed again: deleted for good.
        $this->clear($backend);
        $this->withoutTrashScope($backend);
        $trashed = $this->find($backend, Thread::class, $id);
        if (null !== $trashed) {
            $em->remove($trashed);
            $em->flush();
        }
    }
}
