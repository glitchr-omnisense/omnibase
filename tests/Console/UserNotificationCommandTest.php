<?php

namespace Tests\Base\Console;

use Base\Entity\User\Notification;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\Http\HttpTestTrait;

/**
 * user:notifications counts an account's notifications (or everyone's), lists them with
 * --show, erases with --clear the read ones older than base.user.notifications.expiry (or
 * --expiry), with --clear-all every read one. Its default, "+30d", read "d" as a time zone. Every run stopped on "Undefined property
 * $baseService" (Base\Console\Command has none).
 */
final class UserNotificationCommandTest extends KernelTestCase
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

    private function command(array $arguments): CommandTester
    {
        $tester = new CommandTester((new Application(static::$kernel))->find('user:notifications'));
        $tester->execute($arguments, ["capture_stderr_separately" => true]); // a ConsoleOutput, as Base\Console\Command asks

        return $tester;
    }

    private function notification(object $user, bool $read, string $sentAt): Notification
    {
        $notification = new Notification('accountGoodbye.success');
        $notification->setUser($user);
        $notification->setIsRead($read);
        $notification->setSentAt(new \DateTime($sentAt));
        $this->entityManager()->persist($notification);

        return $notification;
    }

    public function testItCountsAnAccountsNotifications(): void
    {
        $user = $this->createUser();
        $old = $this->notification($user, true, '-60 days');
        $recent = $this->notification($user, false, '-1 day');
        $this->entityManager()->flush();
        // And the notices the registration sent, written on that flush: a host sends them read
        // (lesringostars' browser+ channel) or unread, never older than +30 days.
        $sent = array_filter($this->entityManager()->getRepository(Notification::class)->findBy(['user' => $user]), fn (Notification $notification) => !\in_array($notification, [$old, $recent], true));
        $sentRead = \count(array_filter($sent, fn (Notification $notification) => $notification->isRead()));

        $tester = $this->command(['--user' => $user->getUserIdentifier()]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertMatchesRegularExpression(\sprintf('/%d notification\(s\) found, %d notification\(s\) are marked as read, 1 notification\(s\) older than \+30 days/', \count($sent) + 2, $sentRead + 1), $tester->getDisplay());
    }

    public function testClearErasesTheReadOnesPastTheExpiry(): void
    {
        $user = $this->createUser();
        $old = $this->notification($user, true, '-60 days');
        $recent = $this->notification($user, true, '-1 day');
        $unread = $this->notification($user, false, '-60 days');
        $this->entityManager()->flush();
        $ids = [$old->getId(), $recent->getId(), $unread->getId()];

        $this->command(['--user' => $user->getUserIdentifier(), '--clear' => true]);
        $this->entityManager()->clear();
        $left = fn () => array_map(fn ($id) => null !== $this->entityManager()->find(Notification::class, $id), $ids);
        $this->assertSame([false, true, true], $left(), 'the old read one only');

        $this->command(['--user' => $user->getUserIdentifier(), '--clear' => true, '--expiry' => '+0 days']);
        $this->entityManager()->clear();
        $this->assertSame([false, false, true], $left(), '--expiry sets the age');
    }

    public function testClearAllErasesEveryReadOne(): void
    {
        $user = $this->createUser();
        $recent = $this->notification($user, true, '-1 day');
        $unread = $this->notification($user, false, '-1 day');
        $this->entityManager()->flush();
        $ids = [$recent->getId(), $unread->getId()];

        $this->command(['--user' => $user->getUserIdentifier(), '--clear-all' => true]);
        $this->entityManager()->clear();

        $this->assertSame([false, true], array_map(fn ($id) => null !== $this->entityManager()->find(Notification::class, $id), $ids));
    }
}
