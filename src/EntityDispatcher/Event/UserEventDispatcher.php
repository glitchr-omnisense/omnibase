<?php

namespace Base\EntityDispatcher\Event;

use App\Entity\User;
use Base\EntityDispatcher\AbstractEventDispatcher;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class UserEventDispatcher extends AbstractEventDispatcher
{
    public function supports(mixed $subject): bool
    {
        return $subject instanceof User;
    }

    public function onPersist(LifecycleEventArgs $event)
    {
        $user = $event->getObject();
        $this->addEvent(UserEvent::REGISTER, $user);
    }

    public function onUpdate(LifecycleEventArgs $event)
    {
        $user = $event->getObject();
        $oldUser = $this->entityHydrator->getOriginalEntity($event);

        if ($user->isApproved() && !$oldUser->isApproved()) {
            $this->addEvent(UserEvent::APPROVAL, $user);
        }

        if ($user->isVerified() && !$oldUser->isVerified()) {
            $this->addEvent(UserEvent::VERIFIED, $user);
        }

        if ($user->isEnabled() && !$oldUser->isEnabled()) {
            $this->addEvent(UserEvent::ENABLED, $user);
        }

        if ($user->isDisabled() && !$oldUser->isDisabled()) {
            $this->addEvent(UserEvent::DISABLED, $user);
        }

        if ($user->isKicked() && !$oldUser->isKicked()) {
            $this->addEvent(UserEvent::KICKED, $user);
        }

        if ($user->isLocked() && !$oldUser->isLocked()) {
            $this->addEvent(UserEvent::LOCKED, $user);
        }

        if ($user->isBanned() && !$oldUser->isBanned()) {
            $this->addEvent(UserEvent::BANNED, $user);
        }

        if (!$user->isGhost() && $oldUser->isGhost()) {
            $this->addEvent(UserEvent::GHOST, $user);
        }
    }
}
