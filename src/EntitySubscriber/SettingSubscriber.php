<?php

namespace Base\EntitySubscriber;

use Base\Entity\Layout\Setting;
use Base\Entity\Layout\SettingIntl;
use Base\Service\SettingBagInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;

/**
 * Invalidates SettingBag's compiled snapshot on any Setting/SettingIntl write,
 * not just ones that went through SettingBag::set(). The admin CRUD
 * (EasyAdmin's SettingCrudController / LayoutSettingListType) persists these
 * entities via a plain flush() and never called SettingBag::set() at all, so
 * without this listener an admin edit (e.g. the site logo) would keep serving
 * the stale cached value until the next unrelated cache:clear.
 */
class SettingSubscriber
{
    /**
     * Lazy closure (service_closure in DI config), NOT the SettingBag itself:
     * Doctrine instantiates event listeners during event-manager
     * initialization, and constructing SettingBag there drags in
     * SettingRepository -> getClassMetadata() -> a re-entrant
     * resolveDiscriminator dispatch that corrupts the event manager's
     * partially-initialized listener state. The closure defers all of that to
     * the first actual Setting write.
     *
     * @param \Closure(): SettingBagInterface $settingBag
     */
    public function __construct(protected \Closure $settingBag)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidateIfRelevant($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidateIfRelevant($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->invalidateIfRelevant($args->getObject());
    }

    private function invalidateIfRelevant(object $entity): void
    {
        if ($entity instanceof Setting || $entity instanceof SettingIntl) {
            ($this->settingBag)()->clearAll();
        }
    }
}
