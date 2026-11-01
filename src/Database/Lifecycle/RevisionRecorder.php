<?php

namespace Base\Database\Lifecycle;

use Base\Database\Attribute\Versionable;
use Base\Entity\Extension\Revision;
use Base\Enum\EntityAction;
use BackedEnum;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Omnibridge\Persistence\UnitOfWork;
use Throwable;

/**
 * What a revision records, whatever persistence writes it (plan "omnibase agnostic", step F2b): the
 * diff of an entity's #[Versionable] properties, their values in a comparable form, and the revision
 * entry - not twice for the same diff in a row.
 *
 * The persistence's part - which entities the flush updates, the to-many collections' snapshots, the
 * revision inserted in the running flush, the oldest ones trimmed - is
 * Base\EntitySubscriber\VersionableSubscriber's on Doctrine (moves to omnibridge/doctrine at step F3).
 */
final class RevisionRecorder
{
    /**
     * The last diff recorded per entity: the same change computed twice in one request (a flush after
     * the flush) gives no second, byte-identical revision.
     *
     * @var array<string, string>
     */
    private array $lastDiff = [];

    public function __construct(private readonly UnitOfWork $unitOfWork)
    {
    }

    /**
     * The versioned properties' diff, keyed by the anchor's prefix and the property.
     *
     * @param array<string, Versionable>                       $versioned
     * @param array<string, array{0: mixed, 1: mixed}>         $changes     the entity's field and to-one changes
     * @param array<string, array{0: iterable, 1: iterable}>   $collections a changed to-many property => [its snapshot, its items]
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function diff(array $versioned, array $changes, array $collections, string $prefix): array
    {
        $diff = [];
        foreach ($versioned as $property => $versionable) {
            if (array_key_exists($property, $changes)) {
                [$old, $new] = $changes[$property];
                $old = $this->normalize($old);
                $new = $this->normalize($new);

                // Equality is judged on the comparable form so that a field
                // whose serialisation carries a save timestamp does not read
                // as edited; what gets stored is still the real value.
                if ($versionable->comparable($old) !== $versionable->comparable($new)) {
                    $diff[$prefix.$property] = [$old, $new];
                }

                continue;
            }

            if (!array_key_exists($property, $collections)) {
                continue;
            }

            [$old, $new] = $collections[$property];
            $old = $this->normalize($old);
            $new = $this->normalize($new);

            if ($old !== $new) {
                $diff[$prefix.$property] = [$old, $new];
            }
        }

        return $diff;
    }

    /**
     * The revision of $target for that diff, or null when it repeats the previous one recorded for the
     * same entity (compared with the previous diff only: A→B, B→A, A→B in one request stays three).
     *
     * The persistence sets its creation date AFTER persisting it (see TrashRecorder::entry()).
     */
    public function revision(string $class, object $target, array $diff): ?Revision
    {
        $fingerprint = md5(json_encode($diff) ?: '');
        $key = $class.'#'.$target->getId();
        if (($this->lastDiff[$key] ?? null) === $fingerprint) {
            return null;
        }
        $this->lastDiff[$key] = $fingerprint;

        $revision = new Revision();
        $revision->setEntityClass($class);
        $revision->setEntityId($target);
        $revision->setAction(EntityAction::UPDATE);
        $revision->setEntityData($diff);

        return $revision;
    }

    /** A value as a revision stores it: scalars, dates as ATOM, enums by value, entities by identifier and label. */
    public function normalize(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        if (is_array($value)) {
            return array_values(array_map(fn ($item) => $this->normalize($item), $value));
        }

        if (is_object($value)) {
            try {
                if ($this->unitOfWork->contains($value)) {
                    return [
                        // A composite identifier as field => value, none (a new entity) as [], as before.
                        'id' => $this->unitOfWork->identifier($value) ?? [],
                        'label' => method_exists($value, '__toString') ? trim((string) $value) : null,
                    ];
                }

                return method_exists($value, '__toString') ? trim((string) $value) : null;
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}
