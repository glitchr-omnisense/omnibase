<?php

namespace Base\Entity\Layout\Attribute\Adapter\Common;

use App\Entity\Marketplace\Sales\Attribute\Scope\TaxonAdapterAbstract;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Annotations\Annotation\Slugify;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Entity\Layout\Attribute\Common\AbstractAttribute;
use Base\Repository\Layout\Attribute\Adapter\Common\AbstractActionAdapterRepository;
use Base\Service\Model\AutocompleteInterface;
use Base\Service\Model\IconizeInterface;
use Base\Validator\Constraints as AssertBase;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Base\Entity\Layout\Attribute\Common\AttributeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Adapter\Common\AbstractAdapterRepository;
use Base\Database\Annotation\Cache;

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
        throw new \InvalidArgumentException("Unsupported value (".(is_object($subject) ? get_class($subject) : gettype($subject)).") provided in ".str_replace("Proxies\__CG__\\", "", static::class));
    }
}
