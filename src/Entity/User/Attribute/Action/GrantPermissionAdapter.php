<?php

namespace Base\Entity\User\Attribute\Action;

use Base\Database\Attribute\Cache;
use Base\Database\Attribute\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractActionAdapter;
use Base\Entity\User;
use Base\Field\Type\SelectType;
use Doctrine\ORM\Mapping as ORM;
use Base\Repository\User\Attribute\Action\GrantPermissionAdapterRepository;

/**
 * Awards one or more fine-grained permission tags.
 *
 * This is the piece that closes the loop the site already had half of.
 * PermissionVoter has been live for a while, resolving custom tags like
 * ARTICLE.PUBLISH from a user and their groups, with "ARTICLE.*" wildcards -
 * but nothing could ever GRANT a tag except an administrator doing it by
 * hand. With this, a score threshold can:
 *
 *     score >= 250  ->  grant "ALLIANCE.SENIOR"  ->  isGranted() says yes
 *
 * so a level becomes a real capability with no code between the two.
 *
 * Returns tags, not Permission entities, and deliberately: resolving a tag to
 * its row is a repository lookup, and an entity that queries is an entity you
 * cannot evaluate offline. The caller resolves and attaches, the same way
 * MarketplaceManager applies the totals its adapters return.
 *
 * @return string[] from apply()
 */
#[ORM\Entity(repositoryClass: GrantPermissionAdapterRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry(value: "action_grantPermission")]
class GrantPermissionAdapter extends AbstractActionAdapter
{
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-key"];
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
        return implode(", ", $this->tags($value));
    }

    public function apply(mixed $value, mixed $subject): mixed
    {
        if (!$subject instanceof User) {
            return parent::apply($value, $subject);
        }

        return $this->tags($value);
    }

    /** @return string[] */
    private function tags(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($tag) => is_string($tag) ? trim($tag) : null,
            $value
        )));
    }
}
