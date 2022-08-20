<?php

namespace Base\Entity\Layout\Attribute\Common;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Entity\Layout\Attribute\Common\BaseAttribute;
use Base\Entity\Layout\Attribute\Abstract\AbstractAttribute;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\Attribute\Common\RuleRepository;

/**
 * @ORM\Entity(repositoryClass=RuleRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 *
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 *
 * @ORM\DiscriminatorColumn( name = "context", type = "string" )
 *     @DiscriminatorEntry(value="attribute_rule")
 */

abstract class RuleAttribute extends BaseAttribute
{
    public static function __iconizeStatic(): ?array { return ['fas fa-gavel']; }

    public function __construct(AbstractAttribute $adapter, mixed $subject)
    {
        $this->setAdapter($adapter);
    }

    public function get(?string $locale = null): mixed { return null; }
    public function set(...$args): self { return $this; }
    public function resolve(?string $locale = null): mixed { return $this->get($locale); }
}