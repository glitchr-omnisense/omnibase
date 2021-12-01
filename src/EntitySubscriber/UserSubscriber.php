<?php

namespace Base\EntitySubscriber;

use App\Entity\User;

use Base\EntityEvent\UserEvent;
use Base\Service\BaseService;

use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

use Exception;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

class UserSubscriber implements EventSubscriber
{
    /**
     * @var BaseService
     */
    private $baseService;
    private $tokenStorage;

    protected array $events;
    public function __construct(BaseService $baseService, TokenStorageInterface $tokenStorage, $dispatcher){

        $this->dispatcher   = $dispatcher;
        $this->baseService  = $baseService;
        $this->tokenStorage = $tokenStorage;
        $this->events       = [];
    }

    public function getSubscribedEvents() : array
    {
        return [
            Events::postUpdate,
            Events::preUpdate,
            Events::postPersist,
            Events::prePersist
        ];
    }

    public function addEvent(User $user, string $event)
    {
        $id = spl_object_id($user);

        if(!array_key_exists($id, $this->events))
            $this->events[$id] = [];

        if(!in_array($event, $this->events[$id]))
            $this->events[$id][$event] = false;
    }

    public function dispatchEvents($user)
    {
        $id = spl_object_id($user);
        if (!array_key_exists($id, $this->events)) return;

	    foreach ($this->events[$id] as $event => $triggered) {

            $this->events[$id][$event] = true;
            if(!$triggered) // Dispatch only once
                $this->dispatcher->dispatch(new UserEvent($user), $event);
        }
    }

    public function prePersist(LifecycleEventArgs $event)
    {
        $user = $event->getObject();
        if (!$user instanceof User) return;

        // Update only if required
        $this->addEvent($user, UserEvent::REGISTER);
    }

    public function preUpdate(PreUpdateEventArgs $event)
    {
        $user = $event->getObject();
        if (!$user instanceof User) return;
        
        // Update only if required
        $oldUser = $this->baseService->getOriginalEntity($event);
        if (!$oldUser instanceof User) return;

        if($user->isApproved() && !$oldUser->isApproved())
            $this->addEvent($user, UserEvent::APPROVAL);

        if($user->isVerified() && !$oldUser->isVerified())
            $this->addEvent($user, UserEvent::VERIFIED);
        
        if($user->isEnabled() && !$oldUser->isEnabled())
            $this->addEvent($user, UserEvent::ENABLED);

        if($user->isDisabled() && !$oldUser->isDisabled())
            $this->addEvent($user, UserEvent::DISABLED);
    }

    public function postPersist(LifecycleEventArgs $event): void
    {
        $user = $event->getObject();
        if (!$user instanceof User) return;

        $this->dispatchEvents($user);
    }

    public function postUpdate(LifecycleEventArgs $event): void
    {
        $user = $event->getObject();
        if (!$user instanceof User) return;

        $this->dispatchEvents($user);
    }

    public function preRemove(LifecycleEventArgs $event): void
    {
        $user = $event->getObject();
        if (!$user instanceof User) return;

        $impersonator = null;
        if ($this->tokenStorage->getToken() instanceof SwitchUserToken)
            $impersonator = $this->tokenStorage->getToken()->getOriginalToken()->getUser();

        if($impersonator == $this->tokenStorage->getToken()->getUser() || $user == $this->tokenStorage->getToken()->getUser())
            throw new Exception("Unauthorized action: you can't delete your own account");
    }

}
