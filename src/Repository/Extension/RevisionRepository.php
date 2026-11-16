<?php

namespace Base\Repository\Extension;

use Base\Entity\Extension\Revision;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Repository\Extension\Revisions;

/**
 * @method Revision|null find($id)
 * @method Revision|null findOneBy(array $criteria, array $orderBy = null)
 * @method Revision[]    findAll()
 * @method Revision[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RevisionRepository extends ServiceEntityRepository implements Revisions
{
    /**
     * History of one entity, newest first.
     *
     * Ordered by id, not createdAt: the id is the insertion sequence and is
     * strictly monotonic, whereas two revisions written in the same second are
     * otherwise unorderable - and #[Timestamp] has been seen to hand out
     * timestamps that disagree with insertion order outright.
     *
     * @return Revision[]
     */
    public function history(string $entityClass, int $entityId, ?int $limit = null): array
    {
        return $this->findBy(['entityClass' => $entityClass, 'entityId' => $entityId], ['id' => 'DESC'], $limit);
    }

    /**
     * Ids falling outside the newest $keep revisions of an entity.
     *
     * Returns ids rather than entities so the caller can DELETE them in one
     * statement: trimming runs on every versioned save, and hydrating a
     * history only to discard it would make each save pay for the whole
     * history it is about to shorten.
     *
     * @return int[]
     */
    public function obsoleteIds(string $entityClass, int $entityId, int $keep): array
    {
        if ($keep < 1) {
            return [];
        }

        // Past the $keep newest, a thousand at most per flush.
        $obsolete = $this->findBy(['entityClass' => $entityClass, 'entityId' => $entityId], ['id' => 'DESC'], 1000, $keep);

        return array_map(static fn (Revision $revision): int => (int) $revision->getId(), $obsolete);
    }
}
