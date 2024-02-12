<?php

namespace Base\Entity\Layout\Attribute\Adapter\Common;

use Base\Database\Annotation\DiscriminatorEntry;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\Common\AbstractRuleAdapterRepository;
use Base\Database\Annotation\Cache;
use InvalidArgumentException;

#[ORM\Entity(repositoryClass: AbstractRuleAdapterRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations:"ALL")]
#[DiscriminatorEntry(value: "abstract_rule")]
abstract class AbstractRuleAdapter extends AbstractAdapter implements RuleAdapterInterface
{
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-poll"];
    }

    public function compliesWith(mixed $value, mixed $subject): bool
    {
        throw new InvalidArgumentException("Unsupported value (" . (is_object($subject) ? get_class($subject) : gettype($subject)) . ") provided in " . str_replace("Proxies\__CG__\\", "", static::class));
    }
}
