<?php

namespace Base\Entity\User\Attribute;

use Base\Database\Attribute\Cache;
use Base\Database\Attribute\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Common\AbstractAction;
use Base\Entity\User\Group;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\User\Attribute\GroupActionRepository;

/**
 * What a group DOES for somebody who qualifies for it.
 *
 * The third of the triad, and the half that was missing: scopes decide who a
 * group concerns, rules decide whether they qualify, and actions are what
 * actually happens when they do. Without this the engine could only ever
 * answer yes or no, which is why "reaching level 3 unlocks that alliance"
 * stopped at "is eligible" and never became "has the role".
 *
 * Modelled on the marketplace's DiscountAction: owned by the thing it belongs
 * to, delegating the work to an adapter, and - importantly - COMPUTING rather
 * than mutating. There, MarketplaceManager accumulates what the actions
 * return (`$discount += $action->apply($product)`) and applies the total
 * itself. Group::grantTo() does the same, gathering what every action awards
 * before touching the user once, so an action can be evaluated (previewed,
 * listed, explained) without granting anything.
 */
#[ORM\Entity(repositoryClass: GroupActionRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry(value: "action_group")]
class GroupAction extends AbstractAction
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

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: "actions")]
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
