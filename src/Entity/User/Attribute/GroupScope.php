<?php

namespace Base\Entity\User\Attribute;

use Base\Database\Attribute\Cache;
use Base\Database\Attribute\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Common\AbstractScope;
use Base\Entity\User\Group;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\User\Attribute\GroupScopeRepository;

/**
 * Who a group's rules are about in the first place.
 *
 * Scope and rule answer different questions, and the marketplace evaluates
 * them differently for that reason (MarketplaceManager): scopes are OR-ed -
 * any one matching brings the subject into range - while rules are AND-ed,
 * because every condition has to hold. Scope is "does this concern you",
 * rule is "do you qualify".
 *
 * For a group that means a scope can narrow an alliance to, say, a region or
 * a taxon, and the rules then decide whether a user inside that scope has
 * earned their way in. A group with no scopes concerns everyone, which is the
 * right default for one whose entry is purely a matter of score.
 */
#[ORM\Entity(repositoryClass: GroupScopeRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry(value: "scope_group")]
class GroupScope extends AbstractScope
{
    public function get(?string $locale = null): mixed
    {
        return $this->getValue();
    }

    public function set(...$args): self
    {
        return array_key_exists("value", $args) ? $this->setValue($args["value"]) : $this;
    }

    public function resolve(?string $locale = null): mixed
    {
        return $this->adapter ? $this->adapter->resolve($this->get($locale)) : null;
    }

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: "scopes")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    protected $group;

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): self
    {
        $this->group = $group;

        return $this;
    }
}
