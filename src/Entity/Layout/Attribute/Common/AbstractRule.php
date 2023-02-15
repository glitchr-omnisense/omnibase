<?php

namespace Base\Entity\Layout\Attribute\Common;

use Base\Annotations\Annotation\Uploader;
use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;
use Base\Service\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Common\AbstractRuleRepository;
use Base\Database\Annotation\Cache;

/**
 * @ORM\Entity(repositoryClass=AbstractRuleRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @Cache(usage="NONSTRICT_READ_WRITE", associations="ALL")
 * @ORM\DiscriminatorColumn( name = "context", type = "string" )
 *     @DiscriminatorEntry(value="abstract_rule")
 */
abstract class AbstractRule extends AbstractAttribute implements RuleInterface
{
    public static function __iconizeStatic() : ?array { return ["fas fa-poll"]; }

    /**
     * @ORM\Column(type="array")
     */
    protected $value;
    public function getValue()     { return $this->value; }
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }
}