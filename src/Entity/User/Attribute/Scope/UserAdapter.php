<?php

namespace Base\Entity\User\Attribute\Scope;

use Base\Database\Attribute\Cache;
use Base\Database\Attribute\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractScopeAdapter;
use Base\Entity\User;
use Base\Field\Type\SelectType;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\User\Attribute\Scope\UserAdapterRepository;

/**
 * Narrows something to named people.
 *
 * The scope half of the engine, ported from the marketplace's own UserAdapter
 * - the one that limits a coupon to particular customers. Here it limits a
 * group to particular users, which is what makes an alliance invitational
 * rather than open to anyone who clears its score.
 *
 * The stored value is a User entity, not an id: supports() says so, and the
 * form is an entity picker. That follows the original rather than being a
 * choice - scopes there hold Products, Regions and Users, and comparing rows
 * rather than ids is what lets a scope be checked without a lookup.
 *
 * contains() walks from whatever subject it is handed to the user it is
 * really about, exactly as the marketplace's walks Order and OrderItem to
 * their customer. A Sanction is asked about its user, so a scope can be
 * pointed at moderation records as readily as at people. Anything with no
 * user behind it falls through to the parent, which throws rather than
 * quietly answering false - a scope that cannot judge a subject is a
 * misconfiguration, not a "no".
 */
#[ORM\Entity(repositoryClass: UserAdapterRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry(value: "scope_user")]
class UserAdapter extends AbstractScopeAdapter
{
    public static function __iconizeStatic(): ?array
    {
        return User::__iconizeStatic();
    }

    public static function getType(): string
    {
        return SelectType::class;
    }

    public function getOptions(): array
    {
        return ["class" => User::class];
    }

    public function resolve(mixed $value): mixed
    {
        return $value;
    }

    public function supports(mixed $value): bool
    {
        return $value instanceof User;
    }

    public function contains(mixed $value, mixed $subject): bool
    {
        if (!$value instanceof User) {
            return false;
        }

        if ($subject instanceof User) {
            // Ids, because one side is routinely a Doctrine proxy and === on
            // a proxy against its own entity is not the identity it looks.
            return $subject->getId() !== null && $subject->getId() == $value->getId();
        }

        if ($subject instanceof \Base\Entity\User\Sanction) {
            return $this->contains($value, $subject->getUser());
        }

        return parent::contains($value, $subject);
    }
}
