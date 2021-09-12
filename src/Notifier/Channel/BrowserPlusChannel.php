<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Base\Notifier\Channel;

use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

final class BrowserPlusChannel implements ChannelInterface
{
    
    private $stack;

    public function __construct(RequestStack $stack)
    {
        $this->stack = $stack;
    }

    public function supports(Notification $notification, RecipientInterface $recipient): bool
    {
        return true;
    }

    public function notify(Notification $notification, RecipientInterface $recipient, string $transportName = null): void
    {
        if (null === $request = $this->stack->getCurrentRequest()) return;
        if (!$request->hasSession()) return;

        // Prepare variables
        $type    = $notification->getImportance();
        $message = $notification->getContent();

        // Avoid double flash messages
        // (when using subscriber or redirections for instance)
        $flashBag = $request->getSession()->getFlashBag()->peekAll();
        if (array_key_exists($type, $flashBag)) {

            foreach ($flashBag[$type] as $content)
                if ($message == $content) return;
        }

        // Send notification to flashbag
        $request->getSession()->getFlashBag()->add($notification->getImportance(), $message);
    }
}
