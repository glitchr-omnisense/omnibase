<?php

namespace Base\Security;

use App\Entity\User;
use Base\Entity\User\Notification;
use Base\Service\BaseService;
use Exception;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    protected $translator;
    public function __construct(BaseService $baseService) { $this->translator = $baseService->getTranslator(); }

    public function checkPreAuth(UserInterface $user)
    {
        return false;
        if (!$user instanceof User)
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));

        if ($user->isBanned()) {
            $notification = new Notification("notifications.login.banned");
            $notification->send("danger");
            throw new CustomUserMessageAccountStatusException();
        }
    }

    public function checkPostAuth(UserInterface $user)
    {
        if (!$user instanceof User) return;
    }
}