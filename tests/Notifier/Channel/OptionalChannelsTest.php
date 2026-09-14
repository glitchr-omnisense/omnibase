<?php

namespace Tests\Base\Notifier\Channel;

use Base\Notifier\Channel\InAppChannel;
use Base\Notifier\Channel\PushChannel;
use Base\Service\Push\WebPushService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * Regression coverage for the production incident of 2026-09-14.
 *
 * The notification policy "notify" lists ['inapp', 'push'] unconditionally,
 * and both channels are meant to stay silent when they have nothing to do
 * (no VAPID keys, recipient is not a user). They expressed that through
 * supports() - but Symfony's Notifier THROWS when supports() is false:
 * LogicException('The "push" channel is not supported.'). On a host without
 * VAPID keys every announcement aborted, which kept a scheduled article from
 * being published by the cron, run after run.
 */
class OptionalChannelsTest extends TestCase
{
    private function unconfiguredPush(): WebPushService
    {
        $webPush = $this->createMock(WebPushService::class);
        $webPush->method('isConfigured')->willReturn(false);
        $webPush->expects($this->never())->method('sendToUsers');

        return $webPush;
    }

    private function inAppWithNoMatchingUser(): InAppChannel
    {
        // EntityManagerInterface::getRepository() is typed EntityRepository in ORM 3.
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects($this->never())->method('persist');

        return new InAppChannel($entityManager);
    }

    public function testANotifyPolicySendDoesNotThrowWithoutVapidKeys(): void
    {
        $inApp = $this->inAppWithNoMatchingUser();
        $push = new PushChannel($this->unconfiguredPush(), $inApp);

        // The real Symfony Notifier, exactly as the "notify" policy drives it.
        $notifier = new Notifier(['inapp' => $inApp, 'push' => $push]);

        $notifier->send(new Notification('A new article', ['inapp', 'push']), new Recipient('member@example.com'));

        $this->addToAssertionCount(1); // reaching here is the assertion: no LogicException
    }

    public function testBothChannelsAcceptEveryRecipientAndDecideInNotify(): void
    {
        $inApp = $this->inAppWithNoMatchingUser();
        $push = new PushChannel($this->unconfiguredPush(), $inApp);
        $notification = new Notification('x');
        $recipient = new Recipient('not-a-user@example.com');

        $this->assertTrue($inApp->supports($notification, $recipient));
        $this->assertTrue($push->supports($notification, $recipient));

        $push->notify($notification, $recipient);   // unconfigured: silent
        $inApp->notify($notification, $recipient);  // foreign notification class: silent
    }
}
