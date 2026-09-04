<?php

namespace Base\Entity\User\Attribute\Scope;

use Base\Database\Attribute\Cache;
use Base\Database\Attribute\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractScopeAdapter;
use Base\Entity\User;
use Base\Entity\User\Group;
use Base\Field\Type\SelectType;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\User\Attribute\Scope\GroupAdapterRepository;

/**
 * Narrows something to the members of another group.
 *
 * This is what makes groups chain. An alliance scoped to "Explorers" with a
 * rule of `score >= 250` admits a senior explorer and nobody else, so
 * membership can be built in tiers without any tier knowing the rules of the
 * one below it - the same way the marketplace scopes a coupon to a Taxon and
 * lets the category tree do the work.
 *
 * Membership is read from the SUBJECT rather than from the scoped group, so
 * this asks a user which groups they are in rather than asking a group to
 * enumerate its members. On a group with many members that is the difference
 * between one collection and a table scan, and it is also the direction that
 * survives a user being scoped without the group being loaded.
 *
 * Deliberately not transitive: being in a group that is itself scoped to
 * Explorers does not make you an Explorer. Chaining is a thing you express by
 * pointing a scope at each tier, not something inferred - inferred transitive
 * membership is how a cycle becomes an infinite loop.
 */
#[ORM\Entity(repositoryClass: GroupAdapterRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry(value: "scope_group")]
class GroupAdapter extends AbstractScopeAdapter
{
    public static function __iconizeStatic(): ?array
    {
        return Group::__iconizeStatic();
    }

    public static function getType(): string
    {
        return SelectType::class;
    }

    public function getOptions(): array
    {
        return ["class" => Group::class];
    }

    public function resolve(mixed $value): mixed
    {
        return $value;
    }

    public function supports(mixed $value): bool
    {
        return $value instanceof Group;
    }

    public function contains(mixed $value, mixed $subject): bool
    {
        if (!$value instanceof Group) {
            return false;
        }

        if ($subject instanceof User) {
            foreach ($subject->getGroups() as $group) {
                if ($group->getId() !== null && $group->getId() == $value->getId()) {
                    return true;
                }
            }

            return false;
        }

        if ($subject instanceof Group) {
            return $subject->getId() !== null && $subject->getId() == $value->getId();
        }

        return parent::contains($value, $subject);
    }
}
