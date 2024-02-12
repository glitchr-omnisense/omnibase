<?php

namespace Base\Entity\Layout\Attribute\Adapter\Common;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Repository\Layout\Attribute\Adapter\Common\AbstractScopeAdapterRepository;

use Doctrine\ORM\Mapping as ORM;
use Base\Database\Annotation\Cache;
use InvalidArgumentException;

#[ORM\Entity(repositoryClass: AbstractScopeAdapterRepository::class)]
#[Cache(usage: "NONSTRICT_READ_WRITE", associations: "ALL")]
#[DiscriminatorEntry(value: "abstract_scope" )]
abstract class AbstractScopeAdapter extends AbstractAdapter implements ScopeAdapterInterface
{
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-crosshairs"];
    }

    public function contains(mixed $value, mixed $subject): bool
    {
        throw new InvalidArgumentException("Unsupported value (" . (is_object($subject) ? get_class($subject) : gettype($subject)) . ") provided in " . str_replace("Proxies\__CG__\\", "", static::class));
    }
}
