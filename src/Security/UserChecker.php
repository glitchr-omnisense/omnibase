<?php

namespace Base\Security;

use App\Entity\User;
use Base\Entity\User\Notification;
use Base\Entity\User\Token;
use Base\Service\BaseService;
use Exception;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(BaseService $baseService) 
    {
        $this->baseService = $baseService;
    }

    public function checkPreAuth(UserInterface $user)
    {
        if (!$user instanceof User)
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
    }

    public function checkPostAuth(UserInterface $user)
    {
        if (!$user instanceof User) return;

        if($user->isDisabled()) {

            if( ($welcomeBackToken = $user->getValidToken("welcome-back")) ) {

                throw new CustomUserMessageAccountStatusException(
                    "notifications.accountWelcomeBack.foundToken", 
                    [
                        0 => $this->baseService->getTwigExtension()->time($welcomeBackToken->getRemainingTime()),
                        "importance" => "danger"
                    ]
                );

            } else {

                // Remove expired tokens
                $user->removeExpiredTokens();

                $welcomeBackToken = new Token("welcome-back", 3600);
                $welcomeBackToken->setUser($user);

                $notification = new Notification("notifications.accountWelcomeBack.success", [$user]);
                $notification->setUser($user);
                $notification->setHtmlTemplate("@Base/security/email/account_welcomeBack.html.twig", ["token" => $welcomeBackToken]);
                $notification->send("email");

                $this->baseService->getEntityManager()->flush();
                throw new CustomUserMessageAccountStatusException("notifications.login.disabled", ["importance" => "warning"]);
            }

        }

        if ($user->isBanned())
            throw new CustomUserMessageAccountStatusException("notifications.login.banned", ["importance" => "danger"]);

    }
}