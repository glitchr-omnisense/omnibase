<?php

namespace Base\Entity\User\Attribute;

use Base\Database\Attribute\Cache;
use Base\Database\Attribute\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Common\AbstractRule;
use Base\Entity\User\Group;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\User\Attribute\GroupRuleRepository;

/**
 * A condition a user must satisfy for a group to be open to them.
 *
 * Shaped after the marketplace's DiscountRule rather than as a free-floating
 * rule: a rule belongs to the thing it qualifies, and carries a foreign key
 * to it. That is what lets one rule table serve a discount, a shipping method
 * and a group without any of them knowing about each other - the concrete
 * subclass is the only thing that names an owner.
 *
 * The predicate itself is not here. It lives in the adapter (ScoreAdapter and
 * friends), which is why "reaching level 3 unlocks this alliance" needs no
 * class of its own: it is a GroupRule pointing at a ScoreAdapter whose
 * operation is GREATER_EQUAL.
 */
#[ORM\Entity(repositoryClass: GroupRuleRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry(value: "rule_group")]
class GroupRule extends AbstractRule
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

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: "rules")]
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
