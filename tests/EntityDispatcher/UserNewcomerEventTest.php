<?php

namespace Tests\Base\EntityDispatcher;

use Base\EntityDispatcher\Event\UserEvent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Base\Http\HttpTestTrait;

/**
 * user.newcomer is dispatched when an update makes the account a newcomer again, as each
 * other user.* event is when the account enters its state; it was declared and dispatched by
 * nothing.
 */
final class UserNewcomerEventTest extends KernelTestCase
{
    use HttpTestTrait;

    protected function setUp(): void
    {
        $this->bootHost();
    }

    protected function tearDown(): void
    {
        $this->removeUsers();
        parent::tearDown();
    }

    public function testUserNewcomerIsDispatched(): void
    {
        $user = $this->createUser();
        $user->setIsNewcomer(false);
        $this->entityManager()->flush();

        $heard = [];
        static::getContainer()->get('event_dispatcher')->addListener(UserEvent::NEWCOMER, static function (UserEvent $event) use (&$heard): void {
            $heard[] = $event->getUser();
        });
        $user->setIsNewcomer(true);
        $this->entityManager()->flush();

        self::assertSame([$user], $heard);
    }
}
