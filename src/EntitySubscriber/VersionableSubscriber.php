<?php

namespace Base\EntitySubscriber;

use Base\Database\Attribute\Versionable;
use Base\Entity\Extension\Revision;
use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Lifecycle\RevisionRecorder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\PersistentCollection;

/**
 * Records what changed, for every property carrying `#[Versionable]`.
 *
 * One Revision per entity per flush, not one per field: a save that retitles an
 * article and moves it out of draft is one editorial act and reads as one line
 * of history. entityData is the diff, `{"<field>": [old, new]}`.
 *
 * ## Translations are folded into their parent
 *
 * Everything an editor actually types - title, headline, excerpt, content,
 * keywords - lives on ThreadIntl, one row per locale, not on Thread. Left
 * alone, an article's history would be scattered across itself plus N
 * translation rows, none of which the admin has a page for.
 *
 * So a revision of a TranslationInterface entity is attributed to its
 * translatable parent and its keys are prefixed with the locale:
 *
 *     {"slug": [...], "fr.content": [...], "en.title": [...]}
 *
 * which is also the shape the per-field history badge reads, since the form
 * renders translated fields under their locale too.
 *
 * ## Value normalisation
 *
 * entityData is a JSON column, so entities and collections are reduced to ids
 * (plus a label kept only for display - restoring reads the ids). Dates go to
 * ATOM. Nothing else survives: an un-normalisable value is dropped from the
 * diff rather than allowed to break the flush.
 */
class VersionableSubscriber
{
    /** @var array<int, Revision> revisions awaiting their trim, keyed by spl id */
    protected array $pending = [];

    /**
     * What a revision records, on no persistence (plan "omnibase agnostic", step F2b): the diff, the
     * values' comparable form, the revision entry, and the fingerprint of the last diff written per
     * entity, so a repeat is not recorded twice. This class is its Doctrine part.
     *
     * Why the repeat: Base\EntityDispatcher\AbstractEventDispatcher::postUpdate() calls flush() again
     * from inside executeUpdates(). On that nested flush the collection is STILL in
     * getScheduledCollectionUpdates() with its snapshot not yet retaken, so an unchanged to-many
     * association read as changed a second time and produced a byte-identical revision - two rows, one
     * edit, and two of the five slots max_revisions allows (seen live: ids 130/131).
     */
    protected ?RevisionRecorder $recorder = null;

    protected function recorder(): RevisionRecorder
    {
        return $this->recorder ??= new RevisionRecorder(new DoctrineUnitOfWork($this->entityManager));
    }

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected int $maxRevisions = 5,
    ) {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $entityManager = $event->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        /** @var array<int, array{0: object, 1: array}> $diffs target entity + merged diff, keyed by target spl id */
        $diffs = [];

        $candidates = $unitOfWork->getScheduledEntityUpdates();
        foreach ($unitOfWork->getScheduledCollectionUpdates() as $collection) {
            $owner = $collection->getOwner();
            if ($owner !== null) {
                $candidates[] = $owner;
            }
        }

        foreach ($candidates as $entity) {
            $versioned = Versionable::resolve($entityManager->getClassMetadata(get_class($entity))->getName());
            if (empty($versioned)) {
                continue;
            }

            [$target, $prefix] = Versionable::anchor($entity);
            if ($target === null || $target->getId() === null) {
                continue;
            }

            $diff = $this->diff($unitOfWork, $entity, $versioned, $prefix);
            if (empty($diff)) {
                continue;
            }

            $id = spl_object_id($target);
            $diffs[$id] ??= [$target, []];
            $diffs[$id][1] = array_merge($diffs[$id][1], $diff);
        }

        foreach ($diffs as [$target, $diff]) {
            $className = $entityManager->getClassMetadata(get_class($target))->getName();

            $revision = $this->recorder()->revision($className, $target, $diff);
            if (null === $revision) {
                continue;
            }

            $entityManager->persist($revision);

            // AFTER persist, deliberately. #[Timestamp(on: "create")] would
            // otherwise win - its prePersist overwrites the field - and it
            // memoises its DateTime on the attribute INSTANCE, which
            // AttributeReader caches, so it hands the same moment out forever
            // after. Revisions written seconds apart came out with timestamps
            // that disagreed with their own insertion order (seen live: ids
            // 121-125 alternating between :06 and :07). A history is read
            // chronologically, so a wrong time is worse than no time.
            $revision->setCreatedAt(new DateTime());

            $unitOfWork->computeChangeSet($entityManager->getClassMetadata(Revision::class), $revision);

            $this->pending[spl_object_id($revision)] = $revision;
        }
    }

    /**
     * Keep only the newest `max_revisions` per entity. Done after the flush so
     * the revision this flush just wrote is counted, and issued as DQL so a
     * long history is not hydrated to be thrown away.
     */
    public function postFlush(PostFlushEventArgs $event): void
    {
        if (empty($this->pending)) {
            return;
        }

        $revisions = $this->pending;
        $this->pending = [];

        $entityManager = $event->getObjectManager();
        $trimmed = [];

        foreach ($revisions as $revision) {
            $key = $revision->getEntityClass() . "#" . $revision->getEntityId();
            if (isset($trimmed[$key]) || $revision->getEntityId() === null) {
                continue;
            }
            $trimmed[$key] = true;

            $obsolete = $entityManager->getRepository(Revision::class)
                ->obsoleteIds($revision->getEntityClass(), $revision->getEntityId(), $this->maxRevisions);

            if (empty($obsolete)) {
                continue;
            }

            $entityManager->createQuery(
                "DELETE FROM " . Revision::class . " r WHERE r.id IN (:ids)"
            )->setParameter("ids", $obsolete)->execute();
        }
    }

    /**
     * The entity's changes and its dirty to-many collections, as Doctrine tracks them, given to
     * RevisionRecorder::diff().
     *
     * @param array<string, Versionable> $versioned
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    protected function diff(\Doctrine\ORM\UnitOfWork $unitOfWork, object $entity, array $versioned, string $prefix): array
    {
        $changeSet = $unitOfWork->getEntityChangeSet($entity);
        $classMetadata = $this->entityManager->getClassMetadata(get_class($entity));

        // To-many associations never appear in an entity change set -
        // Doctrine tracks them as collection updates instead, and by the
        // time onFlush runs the snapshot still holds what was loaded.
        $collections = [];
        foreach ($versioned as $property => $_) {
            if (array_key_exists($property, $changeSet)) {
                continue;
            }
            if (!$classMetadata->hasAssociation($property) || !$classMetadata->isCollectionValuedAssociation($property)) {
                continue;
            }

            $collection = $classMetadata->getFieldValue($entity, $property);
            if (!$collection instanceof PersistentCollection || !$collection->isDirty()) {
                continue;
            }

            $collections[$property] = [$collection->getSnapshot(), $collection->toArray()];
        }

        return $this->recorder()->diff($versioned, $changeSet, $collections, $prefix);
    }

    /** RevisionRecorder::normalize(), kept for this class's subclasses. */
    protected function normalize(mixed $value): mixed
    {
        return $this->recorder()->normalize($value);
    }
}
