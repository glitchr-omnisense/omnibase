<?php

namespace Base\Entity\User\Attribute\Action;

use Base\Database\Attribute\Cache;
use Base\Database\Attribute\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractActionAdapter;
use Base\Entity\User;
use Base\Field\Type\SelectType;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\User\Attribute\Action\GrantRoleAdapterRepository;

/**
 * Awards one or more roles to whoever qualified.
 *
 * This is the action behind "passing a level gives access": a group whose
 * rules say `score >= 250` and whose action grants ROLE_ALLIANCE_SENIOR turns
 * a number into an actual capability, without anything hard-coding either the
 * threshold or the role.
 *
 * Returns the roles rather than assigning them, following the marketplace's
 * adapters, which compute an amount and let MarketplaceManager apply the
 * total. Group::grantTo() accumulates across every action and writes to the
 * user once - so listing what a group WOULD give somebody costs nothing and
 * changes nothing.
 *
 * A role already held is still returned; deduplicating is the caller's job,
 * because "what does this action award" and "what would change" are different
 * questions and only the caller knows the user's current roles.
 *
 * @return string[] from apply()
 */
#[ORM\Entity(repositoryClass: GrantRoleAdapterRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry(value: "action_grantRole")]
class GrantRoleAdapter extends AbstractActionAdapter
{
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-user-shield"];
    }

    public static function getType(): string
    {
        return SelectType::class;
    }

    public function getOptions(): array
    {
        return ["multiple" => true];
    }

    public function resolve(mixed $value): mixed
    {
        return implode(", ", $this->roles($value));
    }

    public function apply(mixed $value, mixed $subject): mixed
    {
        // Anything user-shaped, so a subclassed App\Entity\User works too.
        if (!$subject instanceof User) {
            return parent::apply($value, $subject);
        }

        return $this->roles($value);
    }

    /** @return string[] */
    private function roles(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($role) => is_string($role) ? trim($role) : null,
            $value
        )));
    }
}
