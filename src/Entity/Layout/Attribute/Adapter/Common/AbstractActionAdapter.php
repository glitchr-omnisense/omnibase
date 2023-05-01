<?php

namespace Base\Entity\Layout\Attribute\Adapter\Common;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Repository\Layout\Attribute\Adapter\Common\AbstractActionAdapterRepository;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository;
use Base\Database\Annotation\Cache;
use InvalidArgumentException;

/**
 * @ORM\Entity(repositoryClass=AbstractActionAdapterRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry( value = "abstract_action" )
 */
abstract class AbstractActionAdapter extends AbstractAdapter implements ActionAdapterInterface
{
    public static function __iconizeStatic(): ?array
    {
        return ["fa-solid fa-directions"];
    }

    public function apply(mixed $value, mixed $subject): mixed
    {
        throw new InvalidArgumentException("Unsupported value (" . (is_object($subject) ? get_class($subject) : gettype($subject)) . ") provided in " . str_replace("Proxies\__CG__\\", "", static::class));
    }
}
