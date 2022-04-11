<?php

namespace Base\Entity\Layout;

use Base\Database\Annotation\DiscriminatorEntry;
use Base\Database\TranslatableInterface;
use Base\Database\Traits\TranslatableTrait;
use Base\Entity\Layout\Attribute\Abstract\AbstractAttribute;
use Base\Model\IconizeInterface;

use Doctrine\ORM\Mapping as ORM;
use Base\Repository\Layout\AttributeRepository;
use Base\Traits\AttributeTrait;
use Base\Traits\BaseTrait;

/**
 * @ORM\Entity(repositoryClass=AttributeRepository::class)
 * @ORM\InheritanceType( "JOINED" )
 * 
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * 
 * @ORM\DiscriminatorColumn( name = "context", type = "string" )
 *     @DiscriminatorEntry
 */

class Attribute implements TranslatableInterface, IconizeInterface, AttributeInterface
{
    use BaseTrait;
    use AttributeTrait;
    use TranslatableTrait;

    public        function __iconize()       : ?array { return $this->adapter ? $this->adapter->__iconize() : null; } 
    public static function __iconizeStatic() : ?array { return ["fas fa-share-alt"]; }

    public function __construct(AbstractAttribute $adapter, mixed $value = null)
    {
        $this->setAdapter($adapter);
        $this->setValue($value);
    }

    public function resolve(?string $locale = null): mixed 
    {
        return $this->adapter->resolve($this->translate($locale)->getValue()) ?? null;
    }
}