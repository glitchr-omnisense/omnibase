<?php

namespace Base\Database\Lifecycle;

use Base\Entity\Layout\Setting;
use Base\Entity\Layout\SettingIntl;
use Base\Service\SettingBagInterface;
use Omnibridge\Persistence\Lifecycle;

/**
 * The SettingBag's snapshot emptied at every Setting or SettingIntl written, whatever writes it (the
 * admin CRUD's plain flush included), on omnisense/omnibridge's neutral lifecycle events (plan
 * "omnibase agnostic", step F2b). Omnibridge\Doctrine\Omnibase\EntitySubscriber\SettingSubscriber relays Doctrine's to it.
 */
final class SettingsInvalidation
{
    /** @param \Closure(): SettingBagInterface $settingBag lazy: see SettingSubscriber */
    public function __construct(private readonly \Closure $settingBag)
    {
    }

    public function onPostPersist(Lifecycle\PostPersist $event): void
    {
        $this->invalidateIfRelevant($event->entity);
    }

    public function onPostUpdate(Lifecycle\PostUpdate $event): void
    {
        $this->invalidateIfRelevant($event->entity);
    }

    public function onPostRemove(Lifecycle\PostRemove $event): void
    {
        $this->invalidateIfRelevant($event->entity);
    }

    private function invalidateIfRelevant(object $entity): void
    {
        if ($entity instanceof Setting || $entity instanceof SettingIntl) {
            ($this->settingBag)()->clearAll();
        }
    }
}
