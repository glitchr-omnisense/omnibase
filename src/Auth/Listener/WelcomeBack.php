<?php

namespace Base\Auth\Listener;

use Base\Auth\UserAccounts;
use Base\Database\UnitOfWorks;
use Base\Entity\User;
use Base\Entity\User\Notification;
use Base\Entity\User\Token;
use Base\Notifier\Mail\SignInMails;
use Base\Notifier\Notifier;
use Doctrine\ORM\EntityManagerInterface;
use Omniauth\Event\AccountRefused;
use Omnibridge\Persistence\UnitOfWork;

/**
 * A disabled account that proved who it is: the "welcome back" e-mail, with
 * a token good an hour - its next sign-in within that hour enables the
 * account again (UserAccounts::check()). Base\Security\UserChecker's
 * checkPostAuth() under Symfony, on omniauth's AccountRefused event.
 */
class WelcomeBack
{
    public const LIFETIME = 3600;

    protected readonly UnitOfWork $unitOfWork;

    /**
     * @param UnitOfWork|EntityManagerInterface $unitOfWork the entity manager is deprecated (until 6.0): adapted by UnitOfWorks
     * @param Notifier|null    $notifier its welcome-back e-mail (Notifier::userWelcomeBack()); without it, the same e-mail, bare
     * @param SignInMails|null $mails    the e-mail written and sent without Symfony's Notifier (a host without it: omnibridge/laravel)
     */
    public function __construct(
        UnitOfWork|EntityManagerInterface $unitOfWork,
        protected readonly ?Notifier $notifier = null,
        protected readonly ?SignInMails $mails = null,
    ) {
        $this->unitOfWork = UnitOfWorks::of($unitOfWork);
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

        // The e-mail of Notifier::userWelcomeBack() (no template of its own: @Base/security/email/account_welcomeBack.html.twig never existed).
        if (null !== $this->mails) {
            $this->mails->welcomeBack($user, $token);
        } else {
            $notification = $this->notifier?->userWelcomeBack($user, $token) ?? self::bare($user, $token);
            $notification->send('email');
        }

        $this->unitOfWork->flush();
    }

    private static function bare(User $user, Token $token): Notification
    {
        $notification = new Notification('accountWelcomeBack.success', [$user]);
        $notification->setUser($user);
        $notification->setHtmlTemplate('email.html.twig', ['token' => $token]);

        return $notification;
    }

    /** @return array<class-string, string> omniauth's events and the method of this class each calls */
    public static function events(): array
    {
        return [AccountRefused::class => 'onAccountRefused'];
    }
}
