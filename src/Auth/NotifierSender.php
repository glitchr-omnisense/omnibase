<?php

namespace Base\Auth;

use Base\Entity\User;
use Base\Notifier\Notifier;
use Omniauth\Contract\Sender;
use Omniauth\Factor;
use Omniauth\Message;

/**
 * omniauth's Sender on omnibase's Notifier (plan "omniauth", §1.9): a
 * sign-in link (Notifier::loginLink(), the e-mail of the former
 * loginToken()) and the second factor's code (Notifier::emailCode()), by
 * e-mail alone - what the page tells the visitor is the same whether an
 * account goes with the address or not.
 */
class NotifierSender implements Sender
{
    public function __construct(protected readonly Notifier $notifier)
    {
    }

    public function send(object $account, Message $message): void
    {
        if (!$account instanceof User) {
            throw new \InvalidArgumentException(\sprintf('omnibase sends to %s accounts, not %s.', User::class, $account::class));
        }
        $notification = match ($message->factor) {
            Factor::Link => $this->notifier->loginLink($account, $message->secret, $message->until),
            Factor::EmailCode => $this->notifier->emailCode($account, $message->secret, $message->until),
            default => throw new \LogicException(\sprintf('Nothing to send for "%s".', $message->factor->value)),
        };
        $notification->sendBy(['email+']);
    }
}
