<?php

namespace Base\Notifier\Channel;

use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

final class BrowserPlusChannel implements ChannelInterface
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function supports(Notification $notification, RecipientInterface $recipient): bool
    {
        return true;
    }

    public function notify(Notification $notification, RecipientInterface $recipient, string $transportName = null): void
    {
        if (null === $request = $this->requestStack->getCurrentRequest()) {
            return;
        }
        if (!$request->hasSession()) {
            return;
        }

        // Prepare variables
        $type    = $notification->getImportance();
        $message = $notification->getContent();

        // Avoid double flash messages
        // (when using subscriber or redirections for instance)
        $flashBag = $request->getSession()->getFlashBag()->peekAll();
        if (array_key_exists($type, $flashBag)) {
            foreach ($flashBag[$type] as $content) {
                if ($message == $content) {
                    return;
                }
            }
        }

        // Send notification to flashbag
        $request->getSession()->getFlashBag()->add($notification->getImportance(), $message);
    }
}
