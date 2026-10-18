<?php

namespace Tests\Base\Screen;

use Base\Contract\Link;
use Base\Entity\Thread;
use Base\Screen\Builder\Accounts;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A member's profile listing their threads (Accounts::recent()): a thread as
 * the core knows it has no page, and had no __toLink() at all - a profile
 * listing one failed with "Call to undefined method". It answers null now
 * (no core route shows a bare thread) and the profile lists the threads that
 * have a page, the others left out; a thread with a page (a bundle's Post,
 * Topic...) overrides __toLink() with the same signature.
 */
final class RecentThreadsTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    public function testAThreadWithoutAPageIsLeftOutOfAProfile(): void
    {
        $bare = new Thread(null, null, 'Sans page');
        $bare->setState('STATE_PUBLISH');
        self::assertNull($bare->__toLink());
        self::assertNull($bare->__toLink(['utm' => 'x'], Link::ABSOLUTE_URL));

        $paged = new class(null, null, 'Le club de lecture') extends Thread {
            public function __toLink(array $routeParameters = [], int $referenceType = Link::ABSOLUTE_PATH): ?string
            {
                return '/bbs/le-club-de-lecture';
            }
        };
        $paged->setState('STATE_PUBLISH');

        $user = (new \ReflectionClass('App\\Entity\\User'))->newInstanceWithoutConstructor();
        (new \ReflectionProperty('Base\\Entity\\User', 'threads'))->setValue($user, new ArrayCollection([$bare, $paged]));

        $recent = static::getContainer()->get(Accounts::class)->recent($user);
        self::assertCount(1, $recent);
        self::assertSame(['Le club de lecture', '/bbs/le-club-de-lecture'], [$recent[0]->toArray()['title'], $recent[0]->toArray()['url']]);
    }
}
