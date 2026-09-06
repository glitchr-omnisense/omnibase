<?php

namespace Base\Database\Filter;

use Base\Database\Attribute\Trasheable;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Hides soft-deleted rows from every read that goes through the ORM.
 *
 * Registered as "trash_filter" by DoctrineConfigurationPass and enabled in
 * BaseBundle::boot(). Disable it (`$em->getFilters()->disable("trash_filter")`)
 * to see the trash - the
 * trash listing, the restore flow and the purge command all do exactly that.
 *
 * NB: the previous implementation emitted
 *
 *     deletedAt < <now + 1h> OR deletedAt IS NULL
 *
 * which is every row that was ever trashed plus every row that was not, i.e.
 * it hid nothing. That went unnoticed for the same reason nothing else here
 * worked: the filter WAS registered and enabled all along, it just resolved
 * #[Trasheable] with getClassAttributes() on the exact class, which never
 * matches an Article or a Gallery, and returned an empty constraint.
 */
class TrashFilter extends SQLFilter
{
    /**
     * @param ClassMetaData $targetEntity
     * @param $targetTableAlias
     * @return string
     * @throws \Exception
     */
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        // With JOINED inheritance the deletion column lives on the root table
        // only, and Doctrine hands every table of the hierarchy to the filter
        // in turn. Constraining a subclass alias would reference a column that
        // is not on that table. Both SqlWalker and JoinedSubclassPersister
        // already resolve the root before calling in - this is the belt to
        // their braces, and it is what makes the alias safe to use.
        if ($targetEntity->inheritanceType === ClassMetadata::INHERITANCE_TYPE_JOINED
            && $targetEntity->name !== $targetEntity->rootEntityName) {
            return "";
        }

        $trasheable = Trasheable::resolve($targetEntity->getName());
        if (!$trasheable) {
            return "";
        }

        $fieldName = $trasheable->deletedAt;
        if (!$targetEntity->hasField($fieldName)) {
            return "";
        }

        return $targetTableAlias . "." . $targetEntity->getColumnName($fieldName) . " IS NULL";
    }
}
