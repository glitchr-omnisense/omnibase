<?php

namespace Tests\Base\Database\Entity\Extension;

use Base\Entity\Extension\TrashBall;
use Base\Entity\Thread;
use Base\EntitySubscriber\TrasheableSubscriber;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * How long a trashed entity is kept (base.extension.empty_trash) reads as a
 * relative date: "+7 days" by default - what TrasheableSubscriber and the
 * purge command say -, a bare number as a number of days. The default was 5,
 * which new DateTime() refused, so on a host without its own value removing a
 * Thread threw (DateMalformedStringException) instead of putting it in the bin.
 */
final class EmptyTrashTest extends KernelTestCase
{
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

    public function testRemovingAThreadPutsItInTheBinForTheDefaultTime(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists('App\\Entity\\User')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }
        self::bootKernel();
        $container = static::getContainer();
        self::assertSame('+7 days', $container->getParameter('base.extension.empty_trash'), 'the harness sets no value of its own: the default');

        $em = $container->get('doctrine')->getManager();
        $thread = new Thread(null, null, 'Un fil à jeter', 'un-fil-a-jeter-'.bin2hex(random_bytes(3)));
        $em->persist($thread);
        $em->flush();
        $id = $thread->getId();

        $before = new \DateTimeImmutable('+7 days -1 minute');
        $em->remove($thread);
        $em->flush();
        $after = new \DateTimeImmutable('+7 days +1 minute');

        $ball = $em->getRepository(TrashBall::class)->findOneBy(['entityClass' => Thread::class, 'entityId' => $id], ['id' => 'DESC']);
        self::assertNotNull($ball, 'the thread is in the bin');
        $permanent = \DateTimeImmutable::createFromInterface($ball->getPermanentAfter());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $permanent->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $permanent->getTimestamp());

        // Removed again: deleted for good.
        $em->clear();
        $em->getFilters()->isEnabled('trash_filter') && $em->getFilters()->disable('trash_filter');
        $trashed = $em->find(Thread::class, $id);
        if (null !== $trashed) {
            $em->remove($trashed);
            $em->flush();
        }
    }
}
