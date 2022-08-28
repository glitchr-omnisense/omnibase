<?php

namespace Base\Entity\Layout\Attribute\Common;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\AbstractAdapter;
use Base\Service\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Common\AbstractRuleRepository;

/**
 * @ORM\Entity(repositoryClass=AbstractRuleRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 *
 * @ORM\DiscriminatorColumn( name = "context", type = "string" )
 *     @DiscriminatorEntry(value="abstract_rule")
 */
abstract class AbstractRule extends AbstractAttribute implements RuleInterface
{
}