<?php

namespace Base\Subscriber;

use Base\Entity\User as BaseUser;

use App\Repository\UserRepository;
use Base\Repository\User\ConnectionRepository;
use App\Entity\User;

use Base\Security\LoginFormAuthenticator;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Base\Entity\User\Notification;

use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;


/**
 *
 */
class SecuritySubscriber implements EventSubscriberInterface
{
    /**
     * @var UserRepository
     */
    private UserRepository $userRepository;

    /**
     * @var ConnectionRepository
     */
    private ConnectionRepository $connectionRepository;

    public function __construct(
        UserRepository               $userRepository,
        ConnectionRepository         $connectionRepository
    )
    {
        $this->userRepository = $userRepository;
        $this->connectionRepository = $connectionRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => ['onLoginSuccess', -65],
            LoginFailureEvent::class => ['onLoginFailure'],
            LogoutEvent::class => ['onLogout']
        ];
    }

    public function onLoginFailure(LoginFailureEvent $event)
    {
        // retrieve connection and mark as failed
    }

    public function onLoginSuccess(LoginSuccessEvent $event)
    {
        /**
         * @var User $event
         */
        $user = $event->getUser();
        
        // retrieve connection and mark as succeeded

        // cleanup connections...
    }

    public function onLogout(LogoutEvent $event)
    {
        $token = $event->getToken();
        $user = ($token) ? $token->getUser() : null;
        
        // retrieve connection and mark as logout
    }
}
