<?php

namespace Base\Auth\Listener;

use Base\Auth\UserAccounts;
use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Entity\User\Token;
use Doctrine\ORM\EntityManagerInterface;
use Omniauth\Event\AccountRefused;

/**
 * A disabled account that proved who it is: the "welcome back" e-mail, with
 * a token good an hour - its next sign-in within that hour enables the
 * account again (UserAccounts::check()). Base\Security\UserChecker's
 * checkPostAuth() under Symfony, on omniauth's AccountRefused event.
 */
class WelcomeBack
{
    public const LIFETIME = 3600;

    public function __construct(protected readonly EntityManagerInterface $entityManager)
    {
    }

    public function onAccountRefused(AccountRefused $event): void
    {
        $user = $event->account;
        if (UserAccounts::DISABLED !== $event->reason || !$user instanceof User) {
            return;
        }
        $user->removeExpiredTokens();

        $token = new Token(UserAccounts::WELCOME_BACK, self::LIFETIME);
        $token->setUser($user);

        $notification = new Notification('accountWelcomeBack.success', [$user]);
        $notification->setUser($user);
        $notification->setHtmlTemplate('@Base/security/email/account_welcomeBack.html.twig', ['token' => $token]);
        $notification->send('email');

        $this->entityManager->flush();
    }

    /** @return array<class-string, string> omniauth's events and the method of this class each calls */
    public static function events(): array
    {
        return [AccountRefused::class => 'onAccountRefused'];
    }
}
