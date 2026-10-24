<?php

namespace Tests\Base\Controller\Action;

use Base\Bridge\Symfony\ActionArguments;
use Base\Controller\Action\Notification\Delete;
use Base\Controller\Action\Notification\Read;
use App\Entity\User;
use Base\Entity\User\Notification;
use Base\Repository\User\Notifications;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * The bell's read marks and removals, written once for every host (step
 * R29): the account's own notifications, the "notifications" token of the
 * body checked through the host's Tokens, 403 without it or for a visitor;
 * and the body as Symfony gives it to an action (ActionArguments). The
 * kernel booted for the entities' class aliases (App\Entity\User\...), which
 * a stub of the user reads.
 */
class NotificationMarksTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }
        self::bootKernel();
    }

    private function access(?object $user): Access
    {
        $access = $this->createStub(Access::class);
        $access->method('getUser')->willReturn($user);

        return $access;
    }

    private function tokens(): Tokens
    {
        $tokens = $this->createStub(Tokens::class);
        $tokens->method('isValid')->willReturnCallback(static fn (string $intent, ?string $token): bool => 'notifications' === $intent && 'good' === $token);

        return $tokens;
    }

    public function testAVisitorOrAWrongTokenIsRefused(): void
    {
        $notifications = $this->createMock(Notifications::class);
        $notifications->expects($this->never())->method('flush');
        $user = $this->createStub(User::class);

        $this->assertSame(403, (new Read($this->access(null), $this->tokens(), $notifications))(['token' => 'good'])->status);
        $this->assertSame(403, (new Read($this->access($user), $this->tokens(), $notifications))(['token' => 'forged'])->status);
        $this->assertSame(403, (new Delete($this->access($user), $this->tokens(), $notifications))([])->status);
    }

    public function testOneNotificationOfTheAccountIsMarkedAndAnotherRemoved(): void
    {
        $user = $this->createStub(User::class);
        $notification = $this->createMock(Notification::class);
        $notification->method('getId')->willReturn(12);
        $notification->expects($this->once())->method('setIsRead')->with(false);

        $notifications = $this->createMock(Notifications::class);
        $notifications->method('findOneBy')->with(['id' => 12, 'user' => $user])->willReturn($notification);
        $notifications->method('countUnreadFor')->willReturn(3);
        $notifications->expects($this->once())->method('remove')->with($notification);
        $notifications->expects($this->exactly(2))->method('flush');

        $read = (new Read($this->access($user), $this->tokens(), $notifications))(['token' => 'good', 'id' => '12', 'read' => 'false']);
        $this->assertSame(200, $read->status);
        $this->assertSame(['success' => true, 'unread' => 3, 'isRead' => false], $read->data);

        $deleted = (new Delete($this->access($user), $this->tokens(), $notifications))(['token' => 'good', 'id' => 12]);
        $this->assertSame(['success' => true, 'deleted' => [12], 'unread' => 3], $deleted->data);
    }

    public function testEveryUnreadOneIsMarkedRead(): void
    {
        $user = $this->createStub(User::class);
        $first = $this->createMock(Notification::class);
        $first->expects($this->once())->method('setIsRead')->with(true);
        $notifications = $this->createStub(Notifications::class);
        $notifications->method('findBy')->willReturnCallback(static fn (array $criteria): array => $criteria === ['user' => $user, 'isRead' => false] ? [$first] : []);

        $this->assertNull((new Read($this->access($user), $this->tokens(), $notifications))(['token' => 'good'])->data['isRead']);
    }

    public function testSymfonyGivesAnActionThePostedBody(): void
    {
        $resolver = new ActionArguments();
        $body = new ArgumentMetadata('body', 'array', false, true, []);

        $json = Request::create('/notifications/read', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"token":"good","id":12}');
        $this->assertSame([['token' => 'good', 'id' => 12]], [...$resolver->resolve($json, $body)]);

        $form = Request::create('/settings/security-required/skip', 'POST', ['_token' => 'abc']);
        $this->assertSame([['_token' => 'abc']], [...$resolver->resolve($form, $body)]);

        $this->assertSame([], [...$resolver->resolve($form, new ArgumentMetadata('other', 'array', false, false, null))]);
    }
}
