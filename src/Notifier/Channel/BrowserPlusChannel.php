<?php

namespace Base\Notifier\Channel;

use Omnibridge\Contract\Requests;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * A notification shown once on the visitor's next page: a flash message of
 * its importance, through the request contract (plan "omnibase agnostic",
 * F5c: Symfony's flash bag, Laravel's session). Nothing outside a request;
 * the same message twice under one type is shown once.
 */
final class BrowserPlusChannel implements ChannelInterface
{
    private ?Requests $requests;

    public function __construct(#[Autowire(service: Requests::class)] ?Requests $requests)
    {
        $this->requests = $requests;
    }

    public function supports(Notification $notification, RecipientInterface $recipient): bool
    {
        return true;
    }

    public function notify(Notification $notification, RecipientInterface $recipient, ?string $transportName = null): void
    {
        if (null === $this->requests || !$this->requests->has()) {
            return;
        }

        $type = $notification->getImportance();
        $message = $notification->getContent();

        // Avoid double flash messages (when using subscriber or redirections for instance)
        foreach ($this->requests->flashes()[$type] ?? [] as $content) {
            if ($message == $content) {
                return;
            }
        }

        $this->requests->flash($type, $message);
    }
}
