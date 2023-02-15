<?php

namespace Base\Entity\Layout\Attribute\Adapter\Common;

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
use Base\Repository\Layout\Attribute\Adapter\Common\AbstractScopeAdapterRepository;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=AbstractScopeAdapterRepository::class)
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @DiscriminatorEntry( value = "abstract_scope" )
 */

abstract class AbstractScopeAdapter extends AbstractAdapter
{
    public static function __iconizeStatic() : ?array { return ["fas fa-crosshairs"]; }
}