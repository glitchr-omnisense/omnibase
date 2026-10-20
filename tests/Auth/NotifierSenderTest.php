<?php

namespace Tests\Base\Auth;

use Base\Auth\NotifierSender;
use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Notifier\Notifier;
use Omniauth\Factor;
use Omniauth\Message;
use PHPUnit\Framework\TestCase;

/**
 * omniauth's Sender on omnibase's Notifier (plan "omniauth", AU7): a sign-in
 * link is Notifier::loginLink()'s e-mail, the second factor's code
 * Notifier::emailCode()'s, each with the secret and its deadline, by e-mail
 * alone.
 */
final class NotifierSenderTest extends TestCase
{
    protected function setUp(): void
    {
        if (null !== $missing = AuthKit::missing()) {
            self::markTestSkipped($missing);
        }
        if (!class_exists('App\\Entity\\User\\Token')) {
            self::markTestSkipped('An application\'s entities are needed to stand for an account (the omnibase harness, or an application\'s suite).');
        }
    }

    public function testALinkAndACodeAreTheNotifiersEmails(): void
    {
        $user = $this->createMock(User::class);
        $until = new \DateTimeImmutable('+15 minutes');
        $link = $this->createMock(Notification::class);
        $link->expects(self::once())->method('sendBy')->with(['email+']);
        $code = $this->createMock(Notification::class);
        $code->expects(self::once())->method('sendBy')->with(['email+']);
        $notifier = $this->createMock(Notifier::class);
        $notifier->expects(self::once())->method('loginLink')->with($user, 'the-link-token', $until)->willReturn($link);
        $notifier->expects(self::once())->method('emailCode')->with($user, '123456', $until)->willReturn($code);

        $sender = new NotifierSender($notifier);
        $sender->send($user, new Message(Factor::Link, 'the-link-token', $until));
        $sender->send($user, new Message(Factor::EmailCode, '123456', $until));
    }

    public function testNothingElseIsSent(): void
    {
        $this->expectException(\LogicException::class);

        (new NotifierSender($this->createMock(Notifier::class)))->send($this->createMock(User::class), new Message(Factor::Totp, 'x', new \DateTimeImmutable()));
    }
}
