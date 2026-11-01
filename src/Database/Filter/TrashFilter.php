<?php

namespace Base\Database\Filter;

use Base\Database\Scope\TrashScope;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Hides soft-deleted rows from every read that goes through the ORM: Doctrine's
 * compilation of Base\Database\Scope\TrashScope (plan "omnibase agnostic", step
 * F2e), the deletion field => null it gives written as SQL. Moves to
 * omnibridge/doctrine at step F3.
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

        $scope = new TrashScope();
        if (!$scope->appliesTo($targetEntity->getName())) {
            return "";
        }

        $constraints = [];
        foreach ($scope->criteria($targetEntity->getName()) as $fieldName => $value) {
            if (!$targetEntity->hasField($fieldName)) {
                return "";
            }
            // TrashScope gives the deletion field => null: IS NULL.
            $constraints[] = $targetTableAlias . "." . $targetEntity->getColumnName($fieldName) . " IS NULL";
        }

        return implode(" AND ", $constraints);
    }
}
