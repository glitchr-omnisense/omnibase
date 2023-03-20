<?php

namespace Base\Entity\Layout\Attribute\Adapter\Common;

use App\Entity\Marketplace\Sales\Attribute\Scope\TaxonAdapterAbstract;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\Slugify;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Entity\Layout\Attribute\Common\AbstractAttribute;
use Base\Service\Model\AutocompleteInterface;
use Base\Service\Model\IconizeInterface;
use Base\Validator\Constraints as AssertBase;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Base\Entity\Layout\Attribute\Common\AttributeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\Common\AbstractRuleAdapterRepository;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=AbstractRuleAdapterRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry( value = "abstract_rule" )
 */

abstract class AbstractRuleAdapter extends AbstractAdapter implements RuleAdapterInterface
{
    public static function __iconizeStatic(): ?array
    {
        return ["fas fa-poll"];
    }

    public function compliesWith(mixed $value, mixed $subject): bool
    {
        throw new \InvalidArgumentException("Unsupported value (".(is_object($subject) ? get_class($subject) : gettype($subject)).") provided in ".str_replace("Proxies\__CG__\\", "", static::class));
    }
}
