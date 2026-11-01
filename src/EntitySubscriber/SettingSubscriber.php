<?php

namespace Base\EntitySubscriber;

use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Lifecycle\SettingsInvalidation;
use Base\Service\SettingBagInterface;
use Omnibridge\Persistence\Lifecycle;
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

    /** The logic, on the neutral events (plan "omnibase agnostic", step F2b); this class is its Doctrine wiring. */
    protected function invalidation(): SettingsInvalidation
    {
        return $this->invalidation ??= new SettingsInvalidation($this->settingBag);
    }

    private ?SettingsInvalidation $invalidation = null;

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidation()->onPostPersist(new Lifecycle\PostPersist($args->getObject(), new DoctrineUnitOfWork($args->getObjectManager())));
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidation()->onPostUpdate(new Lifecycle\PostUpdate($args->getObject(), new DoctrineUnitOfWork($args->getObjectManager())));
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->invalidation()->onPostRemove(new Lifecycle\PostRemove($args->getObject(), new DoctrineUnitOfWork($args->getObjectManager())));
    }
}
