<?php

namespace Base\EntitySubscriber;

use App\Entity\User;
use Base\Entity\User as BaseUser;

use App\Repository\UserRepository;
use Base\Entity\User\Notification;
use Base\Entity\User\Token;
use Base\EntityDispatcher\Event\UserEvent;
use Base\Notifier\NotifierInterface;
use Base\Routing\RouterInterface;
use Base\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserSubscriber implements EventSubscriberInterface
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var TokenStorage
     */
    protected $tokenStorage;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var NotifierInterface
     */
    protected $notifier;

    public function __construct(NotifierInterface $notifier, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage, RouterInterface $router){

        $this->entityManager = $entityManager;
        $this->router        = $router;
        $this->tokenStorage  = $tokenStorage;
        $this->notifier      = $notifier;
    }

    public static function getSubscribedEvents() : array
    {
        return
        [
            UserEvent::REGISTER => ['onRegistration'],
            UserEvent::APPROVAL => ['onApproval'],
            UserEvent::VERIFIED => ['onVerification'],
            UserEvent::ENABLED  => ['onEnabling'],
            UserEvent::DISABLED => ['onDisabling'],
            UserEvent::KICKED   => ['onKickout']
        ];
    }

    public function onEnabling(UserEvent $event)
    {
        $user = $event->getUser();
        if($this->tokenStorage->getToken()->getUser() != $user) return; // Only notify when user requests itself

        if(!$user instanceof BaseUser) return;

        $notification = $this->notifier->sendUserWelcomeBack($user);
        if($this->tokenStorage->getToken()->getUser() == $user)
            $notification->send("success");
    }

    public function onDisabling(UserEvent $event)
    {
        $user = $event->getUser();
        if($this->tokenStorage->getToken()->getUser() != $user) return; // Only notify when user requests itself

        $notification = $this->notifier->sendUserAccountGoodbye($user);
        $notification->send("success");
    }

    public function onKickout(UserEvent $event) { }

    public function onVerification(UserEvent $event) { }

    public function onRegistration(UserEvent $event)
    {
        $token = $this->tokenStorage->getToken();

        $user = $event->getUser();
        if($token && $token->getUser() != $user) return; // Only notify when user requests itself

        if(!$user instanceof BaseUser) return;
        if ($user->isVerified()) { // Social account connection

            $notification = new Notification("verifyEmail.success");
            $notification->setUser($user);
            $notification->send("success");

        } else {

            /**
             * @var \App\Entity\User\Token
             */
            $verifyEmailToken = new Token('verify-email', 3600);
            $user->addToken($verifyEmailToken);

            $notification = $this->notifier->sendVerificationEmail($user, $verifyEmailToken);
            $notification->send("success");
        }

        $this->router->redirectToRoute("user_profile", [], 302);
    }

    public function onApproval(UserEvent $event)
    {
        $user = $event->getUser();
        if(!$user instanceof BaseUser) return;

        $adminApprovalToken = $user->getValidToken("admin-approval");
        if ($adminApprovalToken) {

            $adminApprovalToken->revoke();
            $notification = $this->notifier->sendAdminsApprovalConfirmation($user);
        }
    }
}
