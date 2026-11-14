<?php

namespace Base\Database\Lifecycle;

use Base\Database\Attribute\Trasheable;
use Base\Entity\Extension\TrashBall;
use Base\Enum\EntityAction;
use DateTime;
use DateTimeInterface;
use Throwable;

/**
 * What a soft delete is, whatever persistence cancels the deletion (plan "omnibase agnostic", step
 * F2b): which removal becomes a trashing, and the trash entry the trash listing and the purge read.
 *
 * The persistence's part - the deletion cancelled, the stamp written, what the removal cascaded to
 * rescued, the second-level cache evicted - is Omnibridge\Doctrine\Omnibase\EntitySubscriber\TrasheableSubscriber's on
 * Doctrine (moves to omnibridge/doctrine at step F3).
 */
final class TrashRecorder
{
    private string $emptyTrash;

    /** @param string|int $emptyTrash base.extension.empty_trash: a relative date ("+7 days"), or a number of days */
    public function __construct(string|int $emptyTrash = '+7 days')
    {
        $this->emptyTrash = self::expiry($emptyTrash);
    }

    /**
     * An expiry as new DateTime() reads it: a bare number of days ("5", 5)
     * becomes "+5 days", anything else is kept (a relative date, "+1 month").
     * The configuration's default was 5 once, and new DateTime("5") throws.
     */
    public static function expiry(string|int|null $value, string $default = '+7 days'): string
    {
        $value = trim((string) $value);
        if ('' === $value) {
            return $default;
        }

        return ctype_digit($value) ? '+'.$value.' days' : $value;
    }

    /**
     * Whether removing a #[Trasheable] entity whose deletion field holds $deletedAt is its purge, deleted
     * for real: it is in the trash already. That is the only escape hatch - emptying the trash and
     * "delete permanently" both work by removing an already-trashed entity. Otherwise the removal
     * becomes a trashing.
     */
    public function isPurge(mixed $deletedAt): bool
    {
        return $deletedAt instanceof DateTimeInterface;
    }

    /**
     * The trash entry of an entity trashed at $deletedAt. Its label is snapshotted rather than resolved
     * at display time: the trash has to stay readable even for an entity whose __toString() depends on
     * translations or on a parent that has since gone.
     *
     * The persistence sets its creation date to $deletedAt AFTER persisting it: #[Timestamp(on: "create")]'s
     * own prePersist would overwrite it, with a DateTime it memoised on a cached attribute instance.
     */
    public function entry(object $entity, string $class, Trasheable $trasheable, DateTime $deletedAt): TrashBall
    {
        $trashBall = new TrashBall();
        $trashBall->setEntityClass($class);
        $trashBall->setEntityId($entity);
        $trashBall->setAction(EntityAction::DELETE);
        $trashBall->setPermanentAfter(new DateTime(null !== $trasheable->expiry ? self::expiry($trasheable->expiry) : $this->emptyTrash));

        $label = null;
        try {
            $label = method_exists($entity, '__toString') ? trim((string) $entity) : null;
        } catch (Throwable) {
            $label = null;
        }

        $trashBall->setEntityData(array_filter([
            'label' => $label ?: null,
            'deletedAt' => $deletedAt->format(DATE_ATOM),
        ]));

        return $trashBall;
    }
}
