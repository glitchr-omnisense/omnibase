<?php

namespace Base\Security;

use App\Entity\User;
use Base\Entity\User\Notification;
use Base\Entity\User\Token;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(EntityManagerInterface $entityManager, protected ?\Base\Notifier\Notifier $notifier = null)
    {
        $this->entityManager = $entityManager;
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (class_implements_interface($user, LoginRestrictionInterface::class)) {
            throw new CustomUserMessageAccountStatusException("@notifications.login.restricted", ["importance" => "danger"]);
        }

        if ($user instanceof User) {
            if ($user->isBanned()) {
                throw new CustomUserMessageAccountStatusException("@notifications.login.banned", ["importance" => "danger"]);
            }
            if ($user->isLocked()) {
                throw new CustomUserMessageAccountStatusException("@notifications.login.locked", ["importance" => "danger"]);
            }
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }
        if (class_implements_interface($user, LoginRestrictionInterface::class)) {
            throw new CustomUserMessageAccountStatusException("@notifications.login.restricted", ["importance" => "danger"]);
        }

        if ($user->isDisabled()) {

            $welcomeBackToken = $user->getValidToken("welcome-back");
            if ($welcomeBackToken) {
                $user->enable();
                return;
            }

            // Remove expired tokens
            $user->removeExpiredTokens();

            $welcomeBackToken = new Token("welcome-back", 3600);
            $welcomeBackToken->setUser($user);

            // Notifier::userWelcomeBack()'s e-mail: @Base/security/email/account_welcomeBack.html.twig never existed.
            if (null !== $this->notifier) {
                $notification = $this->notifier->userWelcomeBack($user, $welcomeBackToken);
            } else {
                $notification = new Notification("accountWelcomeBack.success", [$user]);
                $notification->setUser($user);
                $notification->setHtmlTemplate("email.html.twig", ["token" => $welcomeBackToken]);
            }
            $notification->send("email");

            $this->entityManager->flush();
            throw new CustomUserMessageAccountStatusException("@notifications.login.disabled", ["importance" => "warning"]);
        }
    }
}
